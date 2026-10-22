<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioExecution;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require_once __DIR__.'/phase4b_import129_fixture.php';
$factory = new FiscalEngineFactory;
$rtc = new RtcScenarioRunService($factory);
foreach (['domestic', 'import', 'import-platform', 'import-simples', 'recipient-simples', 'recipient-regime-missing'] as $name) {
    foreach (['rtc', 'integrated'] as $runner) {
        $prior = null;
        foreach (in_array($name, ['domestic', 'import', 'import-platform'], true) ? ['129', '131'] : ['131'] as $version) {
            $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.22-'.$version.($version === '131' ? '-draft2.json' : '-draft1.json'));
            if ($name === 'domestic') {
                $source = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/consumption-phase4b-2026.10.02-1/input-credit-standard-2026.json')), true, 512, JSON_THROW_ON_ERROR);
            } else {
                $overrides = $name === 'import-simples' ? ['rtc.acquirer_regime' => 'SIMPLES_NACIONAL']
                    : ($name === 'recipient-simples' ? ['rtc.recipient_regime' => 'SIMPLES_NACIONAL'] : []);
                [$ignored, $source] = phase4bImport129Fixture($factory, $overrides, str_starts_with($name, 'recipient'));
            }
            if ($name === 'import-platform') {
                $source['facts'] = [...$source['facts'], 'rtc.platform_id' => 'PLATFORM-131', 'rtc.platform_remote_intermediation' => true,
                    'rtc.platform_controlled_elements' => ['PAYMENT'], 'rtc.platform_activity_exclusion' => 'NONE',
                    'rtc.platform_qualification_evidence_id' => 'SYNTHETIC-131-PLATFORM'];
            }
            $source['scenario_id'] = 'SCHEDULE131-SYNTHETIC-'.$name;
            $source['rule_bundle_id'] = $knowledge['rule_bundle_id'];
            $source['catalog_versions'] = $knowledge['catalog_versions'];
            $source['knowledge_at'] = '2026-10-22T18:00:00+00:00';
            $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
            $result = $runner === 'rtc' ? $rtc->evaluate($knowledge, $loaded) : $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input);
            $snapshot = (new ScenarioExecution($loaded, $result, $knowledge))->snapshot();
            $a = $runner === 'rtc' ? $snapshot['result'] : array_column($snapshot['result']['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
            $expectedReason = $name === 'recipient-regime-missing' ? 'RTC_DOCUMENT131_TAXPAYER_REGIME_REQUIRED' : 'RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN';
            if (($name === 'domestic' && $a['status'] !== 'VALID') || ($name !== 'domestic' && $a['reason_codes'] !== [$expectedReason])) {
                throw new RuntimeException('Unexpected native schedule result: '.json_encode($a['reason_codes']));
            }
            $comparison = array_intersect_key($a, array_flip(['status', 'reason_codes', 'missing_facts', 'decision']));
            unset($comparison['decision']['document']['obligation']);
            if (isset($comparison['decision']['catalog_versions']['codes'])) {
                $comparison['decision']['catalog_versions']['codes'] = 'EXPLICIT_VERSION_COMPARISON';
            }
            if ($prior !== null && CanonicalJson::hash($prior) !== CanonicalJson::hash($comparison)) {
                throw new RuntimeException('Undeclared fiscal decision change.');
            }
            $prior = $comparison;
            $restored = $factory->scenarioLoaderFromKnowledge($snapshot['knowledge'])->fromArray($snapshot['input']);
            $matches = $runner === 'rtc' ? $rtc->replay($snapshot)
                : hash_equals($result->hash(), $factory->makeFromKnowledge($snapshot['knowledge'])->evaluate($restored->input)->hash());
            if (! $matches) {
                throw new RuntimeException('Replay failed.');
            }
            echo json_encode(['kind' => 'snapshot', 'case' => $name, 'runner' => $runner, 'version' => $version,
                'replay_matches' => $matches, 'declared_schedule_changes_only' => true, 'snapshot' => $snapshot],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
        }
    }
}
