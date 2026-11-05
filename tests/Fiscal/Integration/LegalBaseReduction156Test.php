<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\LegalBaseReduction156;

require_once dirname(__DIR__, 3).'/scripts/agent_a/arithmetic156_fixture.php';

/** Synthetic classifier candidate; never relabels an installed SaaS rule as passenger transport.
 * @return array{0: array<string, mixed>, 1: array<string, mixed>}
 */
function baseReduction156(string $amount = '1000.00'): array
{
    [$input, $knowledge] = arithmetic156('saas', 2026);
    $original = $input['facts']['service.canonical_id'];
    $input['facts']['service.canonical_id'] = 'SYNTHETIC_PASSENGER156';
    $input['facts']['rtc.gross_amount'] = $input['facts']['operation.amount'] = $amount;
    $codes = &$knowledge['documents']['rtc_code_catalog'];
    $codes[LegalBaseReduction156::KEY] = LegalBaseReduction156::POLICY;
    foreach ($codes['engine_decision_rules'] as $rule) {
        if (($rule['canonical_service_id'] ?? null) === $original && ($rule['market'] ?? null) === 'DOMESTIC') {
            $rule['id'] = 'SYNTHETIC.PASSENGER.RULE156';
            $rule['canonical_service_id'] = 'SYNTHETIC_PASSENGER156';
            $rule['legal_base_reduction_family'] = 'INTERNATIONAL_PASSENGER_TRANSPORT';
            $rule['source_kind'] = 'SYNTHETIC_TEST';
            $codes['engine_decision_rules'][] = $rule;
        }
    }
    unset($codes);
    $input['facts']['rtc.legal_base_reduction_records'] = [evidence156(['contract' => 'LEGAL-BASE-ART12-PAR8-A156',
        'rule' => 'LC214_ART12_PAR8', 'service_family' => 'INTERNATIONAL_PASSENGER_TRANSPORT',
        'service_id' => 'SYNTHETIC_PASSENGER156', 'scope_confirmed' => true, 'sold_together' => true,
        'joint_sale_id' => 'SALE156', 'sold_on' => '2026-09-20', 'currency' => 'BRL', 'charged_amount' => $amount,
        'document_id' => $input['facts']['rtc.document_id'], 'supplier_id' => $input['facts']['rtc.supplier_id'],
        'acquirer_id' => $input['facts']['rtc.acquirer_id'],
        'legs' => [['id' => 'OUT156', 'from_country' => 'BR', 'to_country' => 'US', 'departure_on' => '2026-10-01', 'joint_sale_id' => 'SALE156'],
            ['id' => 'BACK156', 'from_country' => 'US', 'to_country' => 'BR', 'departure_on' => '2026-10-20', 'joint_sale_id' => 'SALE156']]])];

    return [$input, refresh156($knowledge)];
}

it('reduces the charged base once by the legal half without changing any tax rate', function (string $amount, string $expected): void {
    [$input, $knowledge] = baseReduction156($amount);
    $result = evaluate156($input, $knowledge);
    $trace = array_column($result['trace'], null, 'stage');
    expect($trace)->toHaveKey('LEGAL_BASE_REDUCTION156');
    $reduction = $trace['LEGAL_BASE_REDUCTION156'];
    expect($reduction['status'])->toBe('RESOLVED')->and($reduction['taxable_base'])->toBe($expected)
        ->and($reduction['rate_reduction_applied'])->toBeFalse();
    $decision = $trace['SYNTHETIC_ARITHMETIC156']['candidate_decision'];
    expect($decision['base']['taxable'])->toBe($expected)->and($decision['ibs']['state_rate_percent'])->toBe('1.25')
        ->and($decision['ibs']['municipal_rate_percent'])->toBe('0.75')->and($decision['cbs']['federal_rate_percent'])->toBe('3.50')
        ->and($result['decision'])->toBe([])->and($result['official'])->toBeFalse();
})->with([['1000.00', '500.00'], ['1000.01', '500.01'], ['0.01', '0.01']]);

it('refuses material contradictions even after the evidence hash is recomputed', function (string $case): void {
    [$input] = baseReduction156();
    $record = &$input['facts']['rtc.legal_base_reduction_records'][0]['record'];
    match ($case) {
        'domestic' => $record['legs'][0]['to_country'] = 'BR',
        'one way' => array_pop($record['legs']),
        'separate sale' => $record['sold_together'] = false,
        'other sale' => $record['legs'][1]['joint_sale_id'] = 'OTHER',
        'return' => $record['legs'][1]['to_country'] = 'AR',
        'dates' => $record['legs'][1]['departure_on'] = '2026-09-30',
        'document' => $record['document_id'] = 'OTHER',
        'supplier' => $record['supplier_id'] = 'OTHER',
        'acquirer' => $record['acquirer_id'] = 'OTHER',
        'service' => $record['service_id'] = 'SOFTWARE',
        'scope' => $record['scope_confirmed'] = false,
        'freight' => $record['service_family'] = 'CARGO_TRANSPORT',
        'amount' => $record['charged_amount'] = '999.00',
        'currency' => $record['currency'] = 'USD',
    };
    $input['facts']['rtc.legal_base_reduction_records'] = [evidence156($record)];
    $result = (new LegalBaseReduction156)->resolve($input['facts'], '1000.00', new DateTimeImmutable($input['knowledge_at']));
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result)->not->toHaveKey('taxable_base');
})->with(['domestic', 'one way', 'separate sale', 'other sale', 'return', 'dates', 'document', 'supplier', 'acquirer', 'service', 'scope', 'freight', 'amount', 'currency']);

it('does not apply passenger reduction to an unqualified software classification', function (): void {
    [$input, $knowledge] = baseReduction156();
    $input['facts']['service.canonical_id'] = 'SOFTWARE_DEVELOPMENT';
    expect(evaluate156($input, $knowledge)['reason_codes'])->toContain('RTC_LEGAL_BASE_REDUCTION_COMBINATION_UNPROVEN');
});
