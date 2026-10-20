<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\AdvanceContext88;

it('binds the advance policy and its lifecycle without bypassing operation qualifications', function (string $mutation, string $reason): void {
    $root = dirname(__DIR__, 3);
    $scenario = json_decode((string) file_get_contents($root.'/storage/app/phase4b-advance88-preparation-20261015/scenarios-v2/past-payment-without-completed-supply.json'), true, 512, JSON_THROW_ON_ERROR);
    $transition = json_decode((string) file_get_contents($root.'/resources/fiscal/catalogs/rtc-transition-2026.10.15-88-draft1.json'), true, 512, JSON_THROW_ON_ERROR);
    $policy = $transition['tax_point_event_policy'];
    $known = new DateTimeImmutable($scenario['knowledge_at']);
    $facts = $scenario['facts'];
    switch ($mutation) {
        case 'missing-contract':
            unset($policy['advance_origin_contract']);
            break;
        case 'wrong-source':
            $policy['source_hashes']['LC214'] = str_repeat('0', 64);
            break;
        case 'source-order':
            $policy['source_hashes'] = array_reverse($policy['source_hashes'], true);
            break;
        case 'before-observation':
            $known = new DateTimeImmutable('2026-10-15T14:11:36.200606+00:00');
            break;
        case 'revoked':
        case 'superseded':
            $policy[$mutation === 'revoked' ? 'revoked_at' : 'superseded_at'] = '2026-10-15T20:00:00+00:00';
            break;
        case 'invalid-lifecycle':
            $policy['revoked_at'] = '2026-02-30T00:00:00+00:00';
            break;
        case 'multiple-contexts':
            $facts['rtc.advance_origin_contexts'][] = $facts['rtc.advance_origin_contexts'][0];
            break;
        case 'missing-selection':
            unset($facts['rtc.tax_point_installment_id']);
            break;
        case 'currency':
            $facts['rtc.gross_currency'] = 'USD';
            break;
        case 'import':
            $facts['rtc.market'] = 'IMPORT';
            break;
        case 'reduced-rate':
            $facts['rtc.tax_treatment'] = 'REDUCED_60';
            break;
        case 'acquirer-collection':
            $facts['rtc.collection_method'] = 'ACQUIRER';
            break;
        case 'missing-supply-proof':
            unset($facts['rtc.advance_origin_contexts'][0]['supply_state_evidence']);
            break;
    }
    $result = (new AdvanceContext88)->qualify($facts, $known, $policy);
    expect($result['reason'])->toBe($reason)->and($result['production_ready'])->toBeFalse()
        ->and($result['posting_authorized'])->toBeFalse();
})->with([
    ['missing-contract', 'RTC_ADVANCE_ORIGIN_POLICY_NOT_MATERIALIZED'],
    ['wrong-source', 'RTC_TAX_POINT_SOURCE_BINDING_INVALID'],
    ['source-order', 'RTC_ADVANCE_ORIGIN_LOCALLY_QUALIFIED'],
    ['before-observation', 'RTC_TAX_POINT_POLICY_NOT_YET_AVAILABLE'],
    ['revoked', 'RTC_TAX_POINT_POLICY_NO_LONGER_AVAILABLE'],
    ['superseded', 'RTC_TAX_POINT_POLICY_NO_LONGER_AVAILABLE'],
    ['invalid-lifecycle', 'RTC_TAX_POINT_POLICY_LIFECYCLE_INVALID'],
    ['multiple-contexts', 'RTC_ADVANCE_SINGLE_CONTEXT_REQUIRED'],
    ['missing-selection', 'RTC_ADVANCE_INSTALLMENT_SELECTION_REQUIRED'],
    ['currency', 'RTC_ADVANCE_CONTEXT_REQUIRES_SEPARATE_TREATMENT'],
    ['import', 'RTC_ADVANCE_CONTEXT_REQUIRES_SEPARATE_TREATMENT'],
    ['reduced-rate', 'RTC_ADVANCE_CONTEXT_REQUIRES_SEPARATE_TREATMENT'],
    ['acquirer-collection', 'RTC_ADVANCE_CONTEXT_REQUIRES_SEPARATE_TREATMENT'],
    ['missing-supply-proof', 'RTC_ADVANCE_NO_SUPPLY_STATE_EVIDENCE_INVALID'],
]);
