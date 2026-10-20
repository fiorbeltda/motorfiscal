<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\RecordedAdvanceContext91;
use App\Modules\Fiscal\ConsumptionReform\TaxPointContext86;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;

it('binds the recorded policy and requalifies the supplied temporal result instead of trusting its marker', function (string $mutation, string $reason): void {
    $folder = base_path('storage/app/phase4b-advance91-integration-20261015');
    $input = json_decode((string) file_get_contents($folder.'/scenarios/recorded-single.json'), true, 512, JSON_THROW_ON_ERROR);
    $transition = json_decode((string) file_get_contents(resource_path('fiscal/catalogs/rtc-transition-2026.10.15-91-draft1.json')), true, 512, JSON_THROW_ON_ERROR);
    $rates = json_decode((string) file_get_contents(resource_path('fiscal/catalogs/rtc-rates-2026.09.25-1.json')), true, 512, JSON_THROW_ON_ERROR);
    $facts = $input['facts'];
    $policy = $transition['tax_point_event_policy'];
    $known = new DateTimeImmutable($input['knowledge_at']);
    $qualified = (new TaxPointContext86)->qualify($facts, $known, $policy);
    switch ($mutation) {
        case 'missing-contract': unset($policy['recorded_advance_contract']);
            break;
        case 'wrong-source': $policy['source_hashes']['LC214'] = str_repeat('0', 64);
            break;
        case 'source-order': $policy['source_hashes'] = array_reverse($policy['source_hashes'], true);
            break;
        case 'before-observation': $known = new DateTimeImmutable('2026-10-15T14:11:36.200606+00:00');
            break;
        case 'revoked': $policy['revoked_at'] = '2026-10-20T00:00:00+00:00';
            break;
        case 'superseded': $policy['superseded_at'] = '2026-10-20T00:00:00+00:00';
            break;
        case 'invalid-lifecycle': $policy['revoked_at'] = '2026-02-30T00:00:00+00:00';
            break;
        case 'input-credit': $facts['rtc.credit_context'] = 'INPUT';
            break;
        case 'wrong-currency': $facts['rtc.gross_currency'] = 'USD';
            break;
        case 'fake-resolved': $qualified['operation_id'] = 'OTHER';
            break;
        case 'missing-plan': unset($qualified['event_plan']);
            break;
        case 'no-reconciliation': $qualified['requires_advance_reconciliation'] = false;
            break;
        case 'two-finals': $facts['rtc.recorded_advance_final_contexts'][] = $facts['rtc.recorded_advance_final_contexts'][0];
            break;
        case 'two-books': $facts['rtc.prior_advance_books'][] = $facts['rtc.prior_advance_books'][0];
            break;
        case 'non-array-capture': $facts['rtc.prior_advance_captures'] = ['UNPROVEN'];
            break;
        case 'contradictory-supply-owner':
            $facts['rtc.tax_point_contexts'][0]['actual_supply']['supplier_id'] = 'OTHER';
            $qualified = (new TaxPointContext86)->qualify($facts, $known, $policy);
            break;
    }
    $result = (new RecordedAdvanceContext91(new TemporalRateCatalog($rates)))->reconcile($facts, $qualified, $known, $policy);
    expect($result['status'] === 'LOCAL_CAPTURED_ADVANCE_FINAL_RECONCILIATION_ONLY' ? 'LOCAL' : $result['reason'])
        ->toBe($reason)->and($result['production_ready'])->toBeFalse()->and($result['posting_authorized'])->toBeFalse();
})->with([
    ['missing-contract', 'RTC_RECORDED_ADVANCE_POLICY_NOT_MATERIALIZED'],
    ['wrong-source', 'RTC_TAX_POINT_SOURCE_BINDING_INVALID'], ['source-order', 'LOCAL'],
    ['before-observation', 'RTC_TAX_POINT_POLICY_NOT_YET_AVAILABLE'],
    ['revoked', 'RTC_TAX_POINT_POLICY_NO_LONGER_AVAILABLE'], ['superseded', 'RTC_TAX_POINT_POLICY_NO_LONGER_AVAILABLE'],
    ['invalid-lifecycle', 'RTC_TAX_POINT_POLICY_LIFECYCLE_INVALID'],
    ['input-credit', 'RTC_TAX_POINT_CONTEXT_REQUIRES_SEPARATE_TREATMENT'],
    ['wrong-currency', 'RTC_RECORDED_ADVANCE_FINAL_CONTEXT_REQUIRED'],
    ['fake-resolved', 'RTC_RECORDED_ADVANCE_QUALIFIER_RESULT_BINDING_INVALID'],
    ['missing-plan', 'RTC_RECORDED_ADVANCE_QUALIFIER_RESULT_BINDING_INVALID'],
    ['no-reconciliation', 'RTC_RECORDED_ADVANCE_FINAL_CONTEXT_REQUIRED'],
    ['two-finals', 'RTC_RECORDED_ADVANCE_SINGLE_FINAL_BOOK_AND_CAPTURE_SET_REQUIRED'],
    ['two-books', 'RTC_RECORDED_ADVANCE_SINGLE_FINAL_BOOK_AND_CAPTURE_SET_REQUIRED'],
    ['non-array-capture', 'RTC_RECORDED_ADVANCE_SINGLE_FINAL_BOOK_AND_CAPTURE_SET_REQUIRED'],
    ['contradictory-supply-owner', 'RTC_RECORDED_ADVANCE_QUALIFIED_SUPPLY_BINDING_INVALID'],
]);
