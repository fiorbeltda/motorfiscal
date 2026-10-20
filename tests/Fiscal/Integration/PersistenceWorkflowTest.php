<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\CatalogImport\DatabaseMunicipalCoverageBuilder;
use App\Modules\Fiscal\Infrastructure\CatalogImport\DatabaseMunicipalRateDiffService;
use App\Modules\Fiscal\Infrastructure\CatalogImport\DatabaseMunicipalRateIntegrityAnalyzer;
use App\Modules\Fiscal\Infrastructure\Files\FactDefinitionFileLoader;
use App\Modules\Fiscal\Infrastructure\Files\RulesValidatorService;
use App\Modules\Fiscal\Infrastructure\Persistence\RulePublisher;
use App\Modules\Fiscal\Infrastructure\Schema\JsonSchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('registers immutable source artifacts idempotently through the headless command', function (): void {
    $arguments = [
        'source-key' => 'SOURCE.SYNTHETIC.COMMAND',
        'version' => 'SYNTHETIC-1',
        'path' => resource_path('fiscal/rules/synthetic/provider-simples.json'),
        '--authority' => 'FIXTURE',
        '--effective-from' => '2026-01-01',
    ];

    $this->artisan('fiscal:source:register', $arguments)->assertSuccessful();
    $this->artisan('fiscal:source:register', $arguments)->assertSuccessful();

    expect(DB::table('fiscal_sources')->count())->toBe(1)
        ->and(DB::table('fiscal_source_versions')->count())->toBe(1)
        ->and(DB::table('fiscal_source_versions')->value('sha256'))->toBe(
            hash_file('sha256', resource_path('fiscal/rules/synthetic/provider-simples.json')),
        );
});

it('publishes immutable rule bundles idempotently', function (): void {
    $sourceId = Str::uuid()->toString();
    $sourceVersionId = Str::uuid()->toString();
    DB::table('fiscal_sources')->insert([
        'id' => $sourceId,
        'source_key' => 'SOURCE.SYNTHETIC.TEST',
        'name' => 'Synthetic test source',
        'authority_type' => 'FIXTURE',
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('fiscal_source_versions')->insert([
        'id' => $sourceVersionId,
        'source_id' => $sourceId,
        'version' => 'SYNTHETIC-1',
        'sha256' => str_repeat('a', 64),
        'original_name' => 'synthetic.json',
        'storage_path' => 'fixtures/synthetic.json',
        'obtained_at' => now(),
        'metadata' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $publisher = new RulePublisher(
        new RulesValidatorService(new JsonSchemaValidator, new FactDefinitionFileLoader),
        DB::connection(),
    );
    $publishedPayload = json_decode(
        (string) file_get_contents(resource_path('fiscal/rules/synthetic/provider-simples.json')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $first = $publisher->publish(
        resource_path('fiscal/rules/synthetic/provider-simples.json'),
        resource_path('fiscal/schemas/rule.schema.json'),
        resource_path('fiscal/facts/core.json'),
        'SYNTHETIC-TEST',
        '1.0.0',
    );
    $second = $publisher->publish(
        resource_path('fiscal/rules/synthetic/provider-simples.json'),
        resource_path('fiscal/schemas/rule.schema.json'),
        resource_path('fiscal/facts/core.json'),
        'SYNTHETIC-TEST',
        '1.0.0',
    );

    expect($first['published'])->toBeTrue()
        ->and($first['idempotent'])->toBeFalse()
        ->and($second['idempotent'])->toBeTrue()
        ->and($second['bundle_hash'])->toBe($first['bundle_hash'])
        ->and($first['bundle_hash'])->toBe(CanonicalJson::hash([$publishedPayload]))
        ->and(DB::table('fiscal_rule_versions')->count())->toBe(1)
        ->and(DB::table('fiscal_rule_bundles')->count())->toBe(1);
});

it('publishes explicit precedence relations with their normative source version', function (): void {
    $sourceId = Str::uuid()->toString();
    $sourceVersionId = Str::uuid()->toString();
    DB::table('fiscal_sources')->insert([
        'id' => $sourceId,
        'source_key' => 'SOURCE.SYNTHETIC.TEST',
        'name' => 'Synthetic test source',
        'authority_type' => 'FIXTURE',
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('fiscal_source_versions')->insert([
        'id' => $sourceVersionId,
        'source_id' => $sourceId,
        'version' => 'SYNTHETIC-1',
        'sha256' => str_repeat('b', 64),
        'original_name' => 'synthetic.json',
        'storage_path' => 'fixtures/synthetic.json',
        'obtained_at' => now(),
        'metadata' => '{}',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fiscal-rules-'.Str::uuid()->toString();
    mkdir($directory);
    $general = json_decode((string) file_get_contents(resource_path('fiscal/rules/synthetic/provider-simples.json')), true, 512, JSON_THROW_ON_ERROR);
    $general['rule_id'] = 'TEST.GENERAL';
    $specific = $general;
    $specific['rule_id'] = 'TEST.SPECIFIC';
    $specific['precedence'] = [[
        'type' => 'EXCEPTION_OF',
        'subordinate_rule_id' => 'TEST.GENERAL',
        'source_id' => 'SOURCE.SYNTHETIC.TEST',
        'source_version' => 'SYNTHETIC-1',
    ]];
    file_put_contents($directory.DIRECTORY_SEPARATOR.'general.json', json_encode($general, JSON_THROW_ON_ERROR));
    file_put_contents($directory.DIRECTORY_SEPARATOR.'specific.json', json_encode($specific, JSON_THROW_ON_ERROR));

    try {
        $report = (new RulePublisher(
            new RulesValidatorService(new JsonSchemaValidator, new FactDefinitionFileLoader),
            DB::connection(),
        ))->publish(
            $directory,
            resource_path('fiscal/schemas/rule.schema.json'),
            resource_path('fiscal/facts/core.json'),
            'SYNTHETIC-PRECEDENCE',
            '1.0.0',
        );

        expect($report['published'])->toBeTrue()
            ->and(DB::table('fiscal_rule_relations')->count())->toBe(1)
            ->and(DB::table('fiscal_rule_relations')->value('source_version_id'))->toBe($sourceVersionId);
    } finally {
        unlink($directory.DIRECTORY_SEPARATOR.'general.json');
        unlink($directory.DIRECTORY_SEPARATOR.'specific.json');
        rmdir($directory);
    }
});

it('persists facts trace and immutable decision snapshots from the command', function (): void {
    $this->artisan('fiscal:scenario:run', [
        'path' => resource_path('fiscal/scenarios/software/valid.json'),
        '--persist' => true,
    ])->assertSuccessful();

    expect(DB::table('fiscal_decision_runs')->count())->toBe(1)
        ->and(DB::table('fiscal_decision_runs')->whereNotNull('scenario_id')->count())->toBe(1)
        ->and(DB::table('fiscal_scenarios')->count())->toBe(1)
        ->and(DB::table('fiscal_fact_snapshots')->count())->toBe(7)
        ->and(DB::table('fiscal_fact_snapshots')->where('source_type', 'DERIVED')->count())->toBe(3)
        ->and(DB::table('fiscal_decisions')->count())->toBe(1)
        ->and(DB::table('fiscal_execution_snapshots')->count())->toBe(1)
        ->and(DB::table('fiscal_decision_traces')->count())->toBeGreaterThan(0);

    $runId = DB::table('fiscal_decision_runs')->value('id');
    expect($runId)->toBeString();
    $this->artisan('fiscal:decision:replay', ['snapshot' => $runId])->assertSuccessful();
});

it('refuses to link a homonymous database bundle with different rule content', function (): void {
    DB::table('fiscal_rule_bundles')->insert([
        'id' => Str::uuid()->toString(),
        'bundle_key' => 'MVP-ISS',
        'version' => '2026.09.16-1',
        'content_hash' => str_repeat('f', 64),
        'status' => 'PUBLISHED',
        'published_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('fiscal:scenario:run', [
        'path' => resource_path('fiscal/scenarios/software/valid.json'),
        '--persist' => true,
    ])->assertFailed();

    expect(DB::table('fiscal_decision_runs')->count())->toBe(0)
        ->and(DB::table('fiscal_scenarios')->count())->toBe(0);
});

it('rejects a persisted replay snapshot whose payload no longer matches its hash', function (): void {
    $this->artisan('fiscal:scenario:run', [
        'path' => resource_path('fiscal/scenarios/software/valid.json'),
        '--persist' => true,
    ])->assertSuccessful();
    $runId = (string) DB::table('fiscal_decision_runs')->value('id');
    $snapshot = json_decode((string) DB::table('fiscal_execution_snapshots')->where('decision_run_id', $runId)->value('payload'), true, 512, JSON_THROW_ON_ERROR);
    $snapshot['input']['scenario_id'] = 'tampered';
    DB::table('fiscal_execution_snapshots')->where('decision_run_id', $runId)->update([
        'payload' => json_encode($snapshot, JSON_THROW_ON_ERROR),
    ]);

    $this->artisan('fiscal:decision:replay', ['snapshot' => $runId])->assertFailed();
});

it('reports municipal catalog changes overlaps and gaps', function (): void {
    $sourceId = Str::uuid()->toString();
    $previousId = Str::uuid()->toString();
    $currentId = Str::uuid()->toString();
    DB::table('fiscal_sources')->insert([
        'id' => $sourceId,
        'source_key' => 'TEST.MUNICIPAL',
        'name' => 'Test municipal source',
        'authority_type' => 'FIXTURE',
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    foreach ([[$previousId, 'previous'], [$currentId, 'current']] as [$id, $version]) {
        DB::table('fiscal_source_versions')->insert([
            'id' => $id,
            'source_id' => $sourceId,
            'version' => $version,
            'sha256' => hash('sha256', $version),
            'original_name' => "{$version}.zip",
            'storage_path' => "fixtures/{$version}.zip",
            'obtained_at' => now(),
            'metadata' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $insertRate = static function (string $sourceVersion, string $key, string $hash, string $service, string $from, ?string $to): void {
        DB::table('municipal_iss_rates')->insert([
            'source_version_id' => $sourceVersion,
            'municipality_ibge' => '3550308',
            'uf' => 'SP',
            'municipality_name' => 'São Paulo',
            'service_code' => $service,
            'ctribnac_code' => $service,
            'municipal_service_code' => '000',
            'incidence_raw' => '1',
            'rate' => '2.000000',
            'effective_from' => $from,
            'effective_to' => $to,
            'validity_key' => $key,
            'raw_row_hash' => $hash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    };
    $insertRate($previousId, 'same', 'same-hash', '010101', '2026-01-01', '2026-12-31');
    $insertRate($previousId, 'changed', 'old-hash', '020201', '2026-01-01', '2026-12-31');
    $insertRate($previousId, 'removed', 'removed-hash', '030301', '2026-01-01', '2026-12-31');
    $insertRate($currentId, 'same', 'same-hash', '010101', '2026-01-01', '2026-12-31');
    $insertRate($currentId, 'changed', 'new-hash', '020201', '2026-01-01', '2026-12-31');
    $insertRate($currentId, 'added', 'added-hash', '040401', '2026-01-01', '2026-12-31');
    $insertRate($currentId, 'period-a', 'a', '999999', '2026-01-01', '2026-01-10');
    $insertRate($currentId, 'period-nested', 'nested', '999999', '2026-01-05', '2026-01-06');
    $insertRate($currentId, 'period-b', 'b', '999999', '2026-01-10', '2026-01-20');
    $insertRate($currentId, 'period-c', 'c', '999999', '2026-02-01', null);

    $diff = (new DatabaseMunicipalRateDiffService(DB::connection()))->compare($previousId, $currentId);
    $integrity = (new DatabaseMunicipalRateIntegrityAnalyzer(DB::connection()))->analyze($currentId);
    $coverage = (new DatabaseMunicipalCoverageBuilder(DB::connection()))->rebuild($currentId);

    expect($diff->toArray())->toBe(['ADDED' => 5, 'CHANGED' => 1, 'REMOVED' => 1, 'UNCHANGED' => 1])
        ->and($integrity->overlaps)->toBe(2)
        ->and($integrity->gaps)->toBe(1)
        ->and($coverage['municipalities'])->toBe(1)
        ->and($coverage['municipality_services'])->toBe(4)
        ->and(DB::table('municipal_coverage_statuses')->value('status'))->toBe('PARTIAL_SOURCE_COVERAGE');
});
