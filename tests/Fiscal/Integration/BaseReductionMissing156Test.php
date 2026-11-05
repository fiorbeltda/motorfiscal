<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\LegalBaseReduction156;

require_once dirname(__DIR__, 3).'/scripts/agent_a/arithmetic156_fixture.php';

it('requires material evidence instead of silently taxing the full passenger base under the new gate', function (): void {
    [$input, $knowledge] = arithmetic156('saas', 2026);
    $codes = &$knowledge['documents']['rtc_code_catalog'];
    $codes[LegalBaseReduction156::KEY] = LegalBaseReduction156::POLICY;
    foreach ($codes['engine_decision_rules'] as &$rule) {
        if (($rule['canonical_service_id'] ?? null) === $input['facts']['service.canonical_id']) {
            $rule['legal_base_reduction_family'] = 'INTERNATIONAL_PASSENGER_TRANSPORT';
        }
    }
    unset($rule, $codes);
    $result = evaluate156($input, $knowledge);
    expect($result['reason_codes'])->toContain('RTC_LEGAL_BASE_REDUCTION_RECORD_REQUIRED')
        ->and($result['missing_facts'])->toContain('rtc.legal_base_reduction_records')->and($result['decision'])->toBe([]);
    // The guard belongs only to a future explicitly activated family, never to accepted B23.
    unset($knowledge['documents']['rtc_code_catalog'][LegalBaseReduction156::KEY]);
    $trace = array_column(evaluate156($input, $knowledge)['trace'], null, 'stage');
    expect($trace['SYNTHETIC_ARITHMETIC156']['candidate_status'])->toBe('VALID');
});
