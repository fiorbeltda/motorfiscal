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
$factory = new FiscalEngineFactory;
$rtc = new RtcScenarioRunService($factory);
$source = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/consumption-phase4b-2026.10.02-1/input-credit-standard-2026.json')), true, 512, JSON_THROW_ON_ERROR);
$knowledge = [];
foreach (['122' => '2026.10.21-122-draft1', '126' => '2026.10.22-126-draft1'] as $version => $label) {
    $knowledge[$version] = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-'.$label.'.json');
}
$cases = [
    'standard' => [[], 'VALID'],
    'reversal' => [['rtc.credit_adjustments' => [['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => '1.00', 'evidence_id' => 'SYNTHETIC-126-E1']]], 'VALID'],
    'disallowance' => [['rtc.credit_adjustments' => [['tax' => 'CBS', 'kind' => 'DISALLOWANCE', 'amount' => '0.25', 'evidence_id' => 'SYNTHETIC-126-E2']]], 'VALID'],
    'invalid-amount' => [['rtc.credit_adjustments' => [['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => true, 'evidence_id' => 'E']]], 'INVALID'],
    'missing-evidence' => [['rtc.credit_adjustments' => [['tax' => 'IBS', 'kind' => 'REVERSAL', 'amount' => '1.00', 'evidence_id' => '']]], 'ACTION_REQUIRED'],
    'new-code-not-classification' => [['rtc.cindop' => '020102'], 'INVALID'],
    // Moving only the knowledge cut to22/10 invalidates the old identity-pair
    // binding. Preserve that refusal;never rewrite evidence to make it positive.
    'partial-export-prices' => [[], 'INVALID', 'scenarios/material-input-prices-2026.10.21-122-v1/000-no-adjustments-gross-before-all-discounts.json'],
    'partial-export-dated' => [[], 'INVALID', 'scenarios/material-input-prices-upstream-2026.10.21-122-v1/000-upstream108-inherit106-inherit105-coincident-material-dates.json'],
];
foreach ($cases as $name => $case) {
    [$overrides, $expected] = $case;
    $caseSource = isset($case[2]) ? json_decode((string) file_get_contents(resource_path('fiscal/'.$case[2])), true, 512, JSON_THROW_ON_ERROR) : $source;
    foreach (['rtc', 'integrated'] as $runner) {
        $previous = null;
        foreach (['122', '126'] as $version) {
            $known = $knowledge[$version];
            $input = $caseSource;
            $input['scenario_id'] = 'CATALOG126-SYNTHETIC-'.$name;
            $input['rule_bundle_id'] = $known['rule_bundle_id'];
            $input['catalog_versions'] = $known['catalog_versions'];
            $input['knowledge_at'] = '2026-10-22T11:00:00+00:00';
            $input['facts'] = array_replace($input['facts'], $overrides);
            $loaded = $factory->scenarioLoaderFromKnowledge($known)->fromArray($input);
            $result = $runner === 'rtc' ? $rtc->evaluate($known, $loaded) : $factory->makeFromKnowledge($known)->evaluate($loaded->input);
            $snapshot = (new ScenarioExecution($loaded, $result, $known))->snapshot();
            $saved = $snapshot['result'];
            $assessment = $runner === 'rtc' ? $saved : array_column($saved['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
            $expectedStatus = $runner === 'integrated' && $expected === 'VALID' ? 'VALID_WITH_WARNINGS' : $expected;
            if ($saved['status'] !== $expectedStatus) {
                throw new RuntimeException($name.': unexpected '.$saved['status'].' '.json_encode($saved['reason_codes']));
            }
            $replayed = $runner === 'rtc' ? $rtc->replay($snapshot)
                : hash_equals($result->hash(), $factory->makeFromKnowledge($known)->evaluate($loaded->input)->hash());
            if (! $replayed) {
                throw new RuntimeException('Replay mismatch: '.$name);
            }
            $compare = ['status' => $assessment['status'], 'decision' => $assessment['decision'],
                'reason_codes' => $assessment['reason_codes'], 'missing_facts' => $assessment['missing_facts']];
            if ($version === '126' && isset($compare['decision']['catalog_versions']['codes'])) {
                $compare['decision']['catalog_versions']['codes'] = '2026.10.21-122-draft1';
            }
            $equal = $previous === null || CanonicalJson::hash($previous) === CanonicalJson::hash($compare);
            if (! $equal) {
                throw new RuntimeException('Undeclared comparison difference: '.$name);
            }
            $previous = $compare;
            $contexts = array_values(array_filter($assessment['trace'], static fn (array $stage): bool => ($stage['stage'] ?? null) === 'CINDOP_REFERENCE126'));
            echo json_encode(['case' => $name, 'runner' => $runner, 'version' => $version,
                'replay_matches' => $replayed, 'fiscal_comparison_122_except_explicit_catalog_version' => $equal,
                'cindop_contexts' => array_column($contexts, 'context'),
                'snapshot' => $snapshot], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION).PHP_EOL;
        }
    }
}
