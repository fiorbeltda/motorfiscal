<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRealizationAnalyzer;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

$root = dirname(__DIR__);
$version = '2026.10.08-28-draft38';
$scenario = json_decode(file_get_contents($root.'/resources/fiscal/scenarios/tvm-third-sale-integrated-'.$version.'/positive-partial-partial-financial.json'), true, 512, JSON_THROW_ON_ERROR);
$catalog = json_decode(file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-'.$version.'.json'), true, 512, JSON_THROW_ON_ERROR);
$manifest = json_decode(file_get_contents($root.'/resources/fiscal/bundles/consumption-phase4b-'.$version.'.json'), true, 512, JSON_THROW_ON_ERROR);
$original = $scenario['facts']['rtc.financial_ledger'][0]['entries'][4]['securities_realization']['analysis_input'];
$rule = $catalog['financial_operation']['base']['securities_realization']['analysis'];
$known = new DateTimeImmutable($manifest['knowledge_available_at']);
$control = (new FinancialSecuritiesRealizationAnalyzer)->analyze($original, $rule, $known);
if ($control['status'] !== 'RECONCILED') {
    throw new RuntimeException('Positive control failed: '.json_encode($control, JSON_THROW_ON_ERROR));
}
$cases = [];
foreach (['swap-origins-preserving-total', 'normative-claim'] as $case) {
    $input = $original;
    $proof = &$input['realizations'][0]['book_release_rounding_evidence'];
    if ($case === 'swap-origins-preserving-total') {
        $covered = $proof['value']['fiscal_covered_rounding_consumed'];
        $uncovered = $proof['value']['uncovered_created_rounding_consumed'];
        if ($covered === $uncovered) {
            throw new RuntimeException('Fixture must have distinct origin consumption.');
        }
        $proof['value']['fiscal_covered_rounding_consumed'] = $uncovered;
        $proof['value']['uncovered_created_rounding_consumed'] = $covered;
    } else {
        $proof['value']['normative_homologation_claimed'] = true;
    }
    $proof['source_hash'] = CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true]));
    unset($proof);
    $result = (new FinancialSecuritiesRealizationAnalyzer)->analyze($input, $rule, $known);
    if ($result['reason'] !== 'RTC_TVM_REALIZATION_INDEPENDENT_THIRD_BOOK_RELEASE_ROUNDING_UNPROVEN' || $result['production_ready'] !== false) {
        throw new RuntimeException($case.': '.json_encode($result, JSON_THROW_ON_ERROR));
    }
    $cases[] = ['case' => $case, 'reason' => $result['reason'], 'production_ready' => false];
}
echo json_encode(['positive_control_resolved' => true, 'cases' => $cases], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
