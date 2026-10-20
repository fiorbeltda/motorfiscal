<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Files\FactDefinitionFileLoader;
use App\Modules\Fiscal\Infrastructure\Files\RulesValidatorService;
use App\Modules\Fiscal\Infrastructure\Persistence\DatabaseRuleBundleRepository;
use App\Modules\Fiscal\Infrastructure\Persistence\RulePublisher;
use App\Modules\Fiscal\Infrastructure\Scenario\DatabaseRuleBundleKnowledgeLoader;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioRunService;
use App\Modules\Fiscal\Infrastructure\Schema\JsonSchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-16 07:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/** @return array<string, mixed> */
function publishMvpRulesForDatabaseExecution(string $bundleKey = 'MVP-ISS', string $bundleVersion = '2026.09.16-1'): array
{
    $references = [];
    foreach (glob(resource_path('fiscal/rules/mvp/*.json')) ?: [] as $path) {
        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $references[(string) $payload['source_id']][(string) $payload['source_version']] = true;
        foreach (($payload['precedence'] ?? []) as $relation) {
            $references[(string) $relation['source_id']][(string) $relation['source_version']] = true;
        }
    }
    foreach ($references as $sourceKey => $versions) {
        $sourceId = Str::uuid7()->toString();
        DB::table('fiscal_sources')->insert([
            'id' => $sourceId,
            'source_key' => $sourceKey,
            'name' => $sourceKey,
            'authority_type' => 'TEST',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach (array_keys($versions) as $version) {
            DB::table('fiscal_source_versions')->insert([
                'id' => Str::uuid7()->toString(),
                'source_id' => $sourceId,
                'version' => $version,
                'sha256' => hash('sha256', $sourceKey.'@'.$version),
                'original_name' => 'database-rule-bundle-test.json',
                'storage_path' => 'tests/database-rule-bundle-test.json',
                'obtained_at' => now(),
                'metadata' => '{}',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    $report = (new RulePublisher(
        new RulesValidatorService(new JsonSchemaValidator, new FactDefinitionFileLoader),
        DB::connection(),
    ))->publish(
        resource_path('fiscal/rules/mvp'),
        resource_path('fiscal/schemas/rule.schema.json'),
        resource_path('fiscal/facts/core.json'),
        $bundleKey,
        $bundleVersion,
    );
    $report['id'] = DB::table('fiscal_rule_bundles')
        ->where('bundle_key', $bundleKey)
        ->where('version', $bundleVersion)
        ->value('id');

    return $report;
}

/** @return list<array<string, mixed>> */
function databaseBundlePayloads(string $bundleId): array
{
    $payloads = DB::table('fiscal_rule_bundle_items as items')
        ->join('fiscal_rule_versions as versions', 'versions.id', '=', 'items.rule_version_id')
        ->join('fiscal_rules as rules', 'rules.id', '=', 'versions.rule_id')
        ->where('items.bundle_id', $bundleId)
        ->orderBy('rules.rule_key')
        ->orderBy('versions.version')
        ->pluck('versions.payload')
        ->map(static fn (mixed $payload): array => json_decode((string) $payload, true, 512, JSON_THROW_ON_ERROR))
        ->all();

    return array_values($payloads);
}

it('loads a published database bundle for scenario execution and snapshot persistence', function (): void {
    $published = publishMvpRulesForDatabaseExecution();
    $loaded = (new DatabaseRuleBundleRepository(DB::connection()))->load('MVP-ISS', '2026.09.16-1');

    expect($loaded->contentHash)->toBe($published['bundle_hash'])
        ->and($loaded->executionId())->toBe('MVP-ISS-2026.09.16-1')
        ->and($loaded->rules)->toHaveCount(6);

    $this->artisan('fiscal:scenario:run', [
        'path' => resource_path('fiscal/scenarios/software/valid.json'),
        '--rule-bundle-key' => 'MVP-ISS',
        '--rule-bundle-version' => '2026.09.16-1',
        '--persist' => true,
    ])->assertSuccessful();

    $snapshot = json_decode(
        (string) DB::table('fiscal_execution_snapshots')->value('payload'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $runId = (string) DB::table('fiscal_decision_runs')->value('id');
    expect(DB::table('fiscal_decision_runs')->value('rule_bundle_id'))->toBe($published['id'])
        ->and($snapshot['knowledge']['rule_bundle_source']['type'])->toBe('DATABASE')
        ->and($snapshot['knowledge']['rule_bundle_source']['content_hash'])->toBe($published['bundle_hash']);
    $this->artisan('fiscal:decision:replay', ['snapshot' => $runId])->assertSuccessful();
});

it('runs a corpus through the explicitly selected database rule bundle', function (): void {
    publishMvpRulesForDatabaseExecution('ALTERNATIVE-ISS', '1.0.0');
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fiscal-db-corpus-'.Str::uuid7()->toString();
    $scenarioRoot = $directory.DIRECTORY_SEPARATOR.'scenarios';
    $expectedRoot = $directory.DIRECTORY_SEPARATOR.'expected';
    mkdir($scenarioRoot.DIRECTORY_SEPARATOR.'software', 0777, true);
    mkdir($expectedRoot.DIRECTORY_SEPARATOR.'software', 0777, true);
    $scenarioPath = $scenarioRoot.DIRECTORY_SEPARATOR.'software'.DIRECTORY_SEPARATOR.'valid.json';
    $expectedPath = $expectedRoot.DIRECTORY_SEPARATOR.'software'.DIRECTORY_SEPARATOR.'valid.json';
    $scenario = json_decode(
        (string) file_get_contents(resource_path('fiscal/scenarios/software/valid.json')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $scenario['rule_bundle_id'] = 'ALTERNATIVE-ISS-1.0.0';
    file_put_contents($scenarioPath, CanonicalJson::encode($scenario));

    $runner = new ScenarioRunService(
        new FiscalEngineFactory,
        resource_path('fiscal'),
        databaseRules: new DatabaseRuleBundleKnowledgeLoader(
            new DatabaseRuleBundleRepository(DB::connection()),
            'ALTERNATIVE-ISS',
            '1.0.0',
        ),
    );
    $result = $runner->run($scenarioPath)->toArray();
    file_put_contents($expectedPath, CanonicalJson::encode([
        'status' => $result['status'],
        'decision_hash' => $result['decision_hash'],
    ]));

    try {
        $this->artisan('fiscal:corpus:run', [
            'path' => $scenarioRoot,
            '--expected' => $expectedRoot,
            '--rule-bundle-key' => 'ALTERNATIVE-ISS',
            '--rule-bundle-version' => '1.0.0',
        ])->assertSuccessful();
    } finally {
        unlink($scenarioPath);
        unlink($expectedPath);
        rmdir($scenarioRoot.DIRECTORY_SEPARATOR.'software');
        rmdir($expectedRoot.DIRECTORY_SEPARATOR.'software');
        rmdir($scenarioRoot);
        rmdir($expectedRoot);
        rmdir($directory);
    }
});

it('rejects persisted payload mutation and bundle hash drift', function (): void {
    $published = publishMvpRulesForDatabaseExecution();
    $ruleVersionId = (string) DB::table('fiscal_rule_bundle_items')
        ->where('bundle_id', $published['id'])
        ->value('rule_version_id');
    $payload = json_decode(
        (string) DB::table('fiscal_rule_versions')->where('id', $ruleVersionId)->value('payload'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $payload['reason_codes'][] = 'TAMPERED';
    DB::table('fiscal_rule_versions')->where('id', $ruleVersionId)->update([
        'payload' => CanonicalJson::encode($payload),
    ]);

    expect(fn () => (new DatabaseRuleBundleRepository(DB::connection()))->load('MVP-ISS', '2026.09.16-1'))
        ->toThrow(RuntimeException::class, 'Persisted rule content hash mismatch');
});

it('rejects a homonymous database bundle whose valid content differs from the local manifest', function (): void {
    $published = publishMvpRulesForDatabaseExecution();
    $ruleVersionId = (string) DB::table('fiscal_rule_bundle_items')
        ->where('bundle_id', $published['id'])
        ->value('rule_version_id');
    $payload = json_decode(
        (string) DB::table('fiscal_rule_versions')->where('id', $ruleVersionId)->value('payload'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $payload['reason_codes'][] = 'ALTERNATIVE_VALID_CONTENT';
    DB::table('fiscal_rule_versions')->where('id', $ruleVersionId)->update([
        'payload' => CanonicalJson::encode($payload),
        'content_hash' => CanonicalJson::hash($payload),
    ]);
    $payloads = databaseBundlePayloads((string) $published['id']);
    DB::table('fiscal_rule_bundles')->where('id', $published['id'])->update([
        'content_hash' => CanonicalJson::hash($payloads),
    ]);

    $loader = new DatabaseRuleBundleKnowledgeLoader(
        new DatabaseRuleBundleRepository(DB::connection()),
        'MVP-ISS',
        '2026.09.16-1',
    );
    expect(fn () => $loader->loadInto((new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'))))
        ->toThrow(RuntimeException::class, 'homonymous local immutable manifest');
});

it('rejects a database rule bundle that references a catalog absent from local knowledge', function (): void {
    $published = publishMvpRulesForDatabaseExecution('ALTERNATIVE-ISS', '1.0.0');
    $ruleVersionId = (string) DB::table('fiscal_rule_bundle_items')
        ->where('bundle_id', $published['id'])
        ->value('rule_version_id');
    $payload = json_decode(
        (string) DB::table('fiscal_rule_versions')->where('id', $ruleVersionId)->value('payload'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $payload['when'] = [
        'fact' => 'service.canonical_id',
        'operator' => 'IN_CATALOG',
        'catalog' => 'CATALOG.NOT.AVAILABLE',
    ];
    DB::table('fiscal_rule_versions')->where('id', $ruleVersionId)->update([
        'payload' => CanonicalJson::encode($payload),
        'content_hash' => CanonicalJson::hash($payload),
    ]);
    $payloads = databaseBundlePayloads((string) $published['id']);
    DB::table('fiscal_rule_bundles')->where('id', $published['id'])->update([
        'content_hash' => CanonicalJson::hash($payloads),
    ]);

    $loader = new DatabaseRuleBundleKnowledgeLoader(
        new DatabaseRuleBundleRepository(DB::connection()),
        'ALTERNATIVE-ISS',
        '1.0.0',
    );
    expect(fn () => $loader->loadInto((new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'))))
        ->toThrow(RuntimeException::class, 'references an unavailable local catalog');
});

it('requires complete database bundle selection in both operational commands', function (): void {
    $this->artisan('fiscal:scenario:run', [
        'path' => resource_path('fiscal/scenarios/software/valid.json'),
        '--rule-bundle-key' => 'MVP-ISS',
    ])->expectsOutputToContain('--rule-bundle-key and --rule-bundle-version must be provided together.')
        ->assertFailed();

    $this->artisan('fiscal:corpus:run', [
        '--rule-bundle-version' => '2026.09.16-1',
    ])->expectsOutputToContain('--rule-bundle-key and --rule-bundle-version must be provided together.')
        ->assertFailed();
});
