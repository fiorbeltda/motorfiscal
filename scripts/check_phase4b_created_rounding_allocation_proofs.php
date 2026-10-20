<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRecognitionHistoryQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;

$root = dirname(__DIR__);
$fixture = json_decode(file_get_contents($root.'/resources/fiscal/scenarios/tvm-created-rounding-closing-2026.10.08-28-draft34/positive-partial-opening.json'), true, 512, JSON_THROW_ON_ERROR);
$catalog = json_decode(file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-2026.10.08-28-draft34.json'), true, 512, JSON_THROW_ON_ERROR);
$manifest = json_decode(file_get_contents($root.'/resources/fiscal/bundles/consumption-phase4b-2026.10.08-28-draft34.json'), true, 512, JSON_THROW_ON_ERROR);
$rule = $catalog['financial_operation']['base']['securities_realization']['analysis']['history_policy'];
$known = new DateTimeImmutable($manifest['knowledge_available_at']);
$seal = static function (array &$proof): void {
    $proof['source_hash'] = CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true]));
};
$moveAllocation = static function (array &$source): void {
    foreach (['retained' => '0.01', 'realized' => '-0.01'] as $edge => $delta) {
        $amounts = &$source['current_fiscal_allocation'][$edge];
        $amounts['assessed_produced_return'] = (string) BigDecimal::of($amounts['assessed_produced_return'])->plus($delta)->toScale(8);
        $amounts['excluded_art195_return'] = (string) BigDecimal::of($amounts['excluded_art195_return'])->minus($delta)->toScale(8);
        unset($amounts);
    }
};
$cases = [];
foreach (['altered-snapshot', 'duplicate-source', 'conserved-allocation-stale-proof', 'normative-claim', 'independent-alternative-allocation'] as $case) {
    $input = $fixture;
    $source = &$input['history']['fiscal_sources'][0];
    $expected = 'RTC_TVM_HISTORY_PUBLIC_CREATED_ROUNDING_CLOSING_UNPROVEN';
    if ($case === 'altered-snapshot') {
        $source['snapshot']['result']['decision_hash'] = str_repeat('0', 64);
        $expected = 'RTC_TVM_HISTORY_FISCAL_SNAPSHOT_REPLAY_MISMATCH';
    } elseif ($case === 'duplicate-source') {
        $input['history']['fiscal_sources'][] = $source;
        $expected = 'RTC_TVM_HISTORY_PUBLIC_CREATED_ROUNDING_CLOSING_POLICY_CONFLICT';
    } elseif ($case === 'normative-claim') {
        $source['created_rounding_closing_evidence']['value']['normative_homologation_claimed'] = true;
        $seal($source['created_rounding_closing_evidence']);
    } else {
        $moveAllocation($source);
        if ($case === 'independent-alternative-allocation') {
            $value = &$source['created_rounding_closing_evidence']['value'];
            $value['current_fiscal_allocation'] = $source['current_fiscal_allocation'];
            $available = $value['opening_fiscal_components'];
            $available['assessed_produced_return'] = (string) BigDecimal::of($available['assessed_produced_return'])->plus('0.01')->toScale(8);
            $available['excluded_art195_return'] = (string) BigDecimal::of($available['excluded_art195_return'])->minus('0.01')->toScale(8);
            $value['opening_fiscal_components'] = $available;
            unset($value);
            $seal($source['created_rounding_closing_evidence']);
            $complete = &$input['history']['complete_evidence'];
            $complete['value']['opening_fiscal_components'] = $available;
            $reference = &$complete['value']['sources'][0];
            $reference['available'] = $available;
            $reference['retained'] = $available;
            $reference['created_rounding_closing_evidence_hash'] = $source['created_rounding_closing_evidence']['source_hash'];
            unset($reference);
            $seal($complete);
            unset($complete);
            $expected = 'RTC_TVM_HISTORY_REPLAY_QUALIFIED';
        }
    }
    unset($source);
    $result = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($input['scope'], $input['history'], $known, $rule);
    if ($result['reason'] !== $expected || $result['production_ready'] !== false) {
        throw new RuntimeException($case.': '.json_encode($result, JSON_THROW_ON_ERROR));
    }
    if ($case === 'independent-alternative-allocation' && $result['opening_fiscal_components'] !== $available) {
        throw new RuntimeException('Independent allocation was replaced by an inferred fraction.');
    }
    $cases[] = ['case' => $case, 'reason' => $result['reason'], 'production_ready' => false];
}
echo json_encode(['cases' => $cases, 'independent_allocation_inference_performed' => false], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
