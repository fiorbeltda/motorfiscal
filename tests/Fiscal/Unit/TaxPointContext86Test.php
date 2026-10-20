<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\TaxPointContext86;

it('binds policy lifecycle and source observation without retrospective availability or hash-order dependence', function (string $mutation): void {
    $folder = dirname(__DIR__, 3).'/storage/app/phase4b-temporal86-integration-preparation-20261015/';
    $scenario = json_decode((string) file_get_contents($folder.'scenarios-v2/expected-date.json'), true, 512, JSON_THROW_ON_ERROR);
    $policy = json_decode((string) file_get_contents($folder.'component-policy.json'), true, 512, JSON_THROW_ON_ERROR);
    $known = new DateTimeImmutable('2026-10-15T23:00:00+00:00');
    $reason = 'RTC_TAX_POINT_DATE_LOCALLY_QUALIFIED';
    switch ($mutation) {
        case 'before-observation':
            $known = new DateTimeImmutable('2026-10-15T14:11:36.200606+00:00');
            $reason = 'RTC_TAX_POINT_POLICY_NOT_YET_AVAILABLE';
            break;
        case 'at-observation':
            $known = new DateTimeImmutable('2026-10-15T14:11:36.200607+00:00');
            break;
        case 'wrong-source':
            $policy['source_hashes']['LC214'] = str_repeat('0', 64);
            $reason = 'RTC_TAX_POINT_SOURCE_BINDING_INVALID';
            break;
        case 'source-order':
            $policy['source_hashes'] = array_reverse($policy['source_hashes'], true);
            break;
        case 'inactive':
            $policy['status'] = 'REVOKED';
            $reason = 'RTC_TAX_POINT_POLICY_UNPROVEN';
            break;
        case 'revoked':
        case 'superseded':
            $policy[$mutation === 'revoked' ? 'revoked_at' : 'superseded_at'] = '2026-10-15T20:00:00+00:00';
            $reason = 'RTC_TAX_POINT_POLICY_NO_LONGER_AVAILABLE';
            break;
        case 'invalid-lifecycle':
            $policy['revoked_at'] = '2026-02-30T20:00:00+00:00';
            $reason = 'RTC_TAX_POINT_POLICY_LIFECYCLE_INVALID';
            break;
        case 'operation-id':
            $scenario['facts']['rtc.tax_point_operation_id'] = 'OTHER';
            $reason = 'RTC_TAX_POINT_OPERATION_DOCUMENT_CONFLICT';
            break;
        case 'two-operations':
            $scenario['facts']['rtc.tax_point_contexts'][] = $scenario['facts']['rtc.tax_point_contexts'][0];
            $reason = 'RTC_TAX_POINT_SINGLE_BOUND_OPERATION_REQUIRED';
            break;
        case 'input-credit':
            $scenario['facts']['rtc.credit_context'] = 'INPUT';
            $reason = 'RTC_TAX_POINT_CONTEXT_REQUIRES_SEPARATE_TREATMENT';
            break;
        case 'reduced-rate':
            $scenario['facts']['rtc.tax_treatment'] = 'REDUCED_60';
            $reason = 'RTC_TAX_POINT_CONTEXT_REQUIRES_SEPARATE_TREATMENT';
            break;
    }
    $result = (new TaxPointContext86)->qualify($scenario['facts'], $known, $policy);
    expect($result['reason'])->toBe($reason)->and($result['production_ready'])->toBeFalse()
        ->and($result['government_evidence_authenticated'])->toBeFalse();
})->with(['before-observation', 'at-observation', 'wrong-source', 'source-order', 'inactive', 'revoked', 'superseded',
    'invalid-lifecycle', 'operation-id', 'two-operations', 'input-credit', 'reduced-rate']);
