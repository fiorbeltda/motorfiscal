<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioExecution;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/phase4b_import129_fixture.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$factory = new FiscalEngineFactory;
$rtc = new RtcScenarioRunService($factory);
$cases = [
    'acquirer' => [[], false, 'RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN'],
    'recipient' => [[], true, 'RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN'],
    'unknown-basis' => [['rtc.collection_legal_basis' => 'UNQUALIFIED'], false, 'RTC_IMPORT129_BASIS_NOT_QUALIFIED'],
    'domestic-basis' => [['rtc.collection_legal_basis' => 'LC214_ART36'], false, 'RTC_IMPORT129_BASIS_NOT_QUALIFIED'],
    'wrong-role' => [['rtc.collection_legal_basis' => 'LC214-ART64-V'], true, 'RTC_IMPORT129_BASIS_ROLE_CONFLICT'],
    'taxpayer-conflict' => [['rtc.taxpayer_id' => 'FOREIGN-SUPPLIER'], false, 'RTC_IMPORT129_TAXPAYER_CONFLICT'],
    'qualified-platform' => [['rtc.platform_id' => 'PLATFORM-129', 'rtc.platform_remote_intermediation' => true,
        'rtc.platform_controlled_elements' => ['PAYMENT'], 'rtc.platform_activity_exclusion' => 'NONE',
        'rtc.platform_qualification_evidence_id' => 'SYNTHETIC-PLATFORM129'], false, 'RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN'],
];
foreach ($cases as $name => [$overrides, $recipient, $expected]) {
    foreach (['rtc', 'integrated'] as $runner) {
        $prior = null;
        foreach (['128', '129'] as $version) {
            [$known, $source] = phase4bImport129Fixture($factory, $overrides, $recipient, $version);
            $source['scenario_id'] = 'IMPORT129-SYNTHETIC-'.$name;
            $loaded = $factory->scenarioLoaderFromKnowledge($known)->fromArray($source);
            $result = $runner === 'rtc' ? $rtc->evaluate($known, $loaded) : $factory->makeFromKnowledge($known)->evaluate($loaded->input);
            $snapshot = (new ScenarioExecution($loaded, $result, $known))->snapshot();
            $saved = $snapshot['result'];
            $assessment = $runner === 'rtc' ? $saved : array_column($saved['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
            $reason = $version === '128' ? 'RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN' : $expected;
            if (! in_array($reason, $assessment['reason_codes'], true)) {
                throw new RuntimeException('Unexpected '.$name.' '.$runner.' '.$version.': '.json_encode($assessment['reason_codes']));
            }
            $stages = array_column($assessment['trace'], null, 'stage');
            if (isset($stages['IMPORT_NOMINAL_CALCULATION'])
                && ($stages['IMPORT_NOMINAL_CALCULATION']['ibs_state'] !== '1.00' || $stages['IMPORT_NOMINAL_CALCULATION']['cbs'] !== '9.00')) {
                throw new RuntimeException('Import monetary calculation changed.');
            }
            if ($prior !== null) {
                // Same literal facts/date; only explicit bundle/catalog context is versioned.
                if (CanonicalJson::hash($prior['facts']) !== CanonicalJson::hash($source['facts'])
                    || $prior['competence_date'] !== $source['competence_date'] || $prior['knowledge_at'] !== $source['knowledge_at']) {
                    throw new RuntimeException('Undeclared input change.');
                }
            }
            $prior = $source;
            $reloaded = $factory->scenarioLoaderFromKnowledge($snapshot['knowledge'])->fromArray($snapshot['input']);
            $matches = $runner === 'rtc' ? $rtc->replay($snapshot)
                : hash_equals($saved['decision_hash'], $factory->makeFromKnowledge($snapshot['knowledge'])->evaluate($reloaded->input)->hash());
            if (! $matches) {
                throw new RuntimeException('Native replay failed.');
            }
            echo json_encode(['kind' => 'snapshot', 'case' => $name, 'runner' => $runner, 'version' => $version,
                'replay_matches' => $matches, 'declared_responsibility_changes_only' => true,
                'comparison_scope' => 'INPUTS_IDENTICAL;EXPECTED_RESPONSIBILITY_TRACE_AND_REFUSALS_VERSIONED',
                'snapshot' => $snapshot], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
        }
    }
}
