<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DereFinancialNegativeBaseSettlementPlanner;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Tests\TestCase;

uses(TestCase::class);

/** Preserved51 composition and50 synthetic prior chain, selected under own52 policy. @return array<string, mixed> */
function settlement52(): array
{
    $capture = json_decode(file_get_contents(storage_path('app/phase4b-negative-base51-current-bases-preparation-20261009/composition-native-result.json')), true, 512, JSON_THROW_ON_ERROR);
    $input = $capture['cases']['explicit-no-iss']['input'];
    $request = json_decode(file_get_contents(storage_path('app/phase4b-negative-base50-closing-draft-20261009/command-negative-base-request.json')), true, 512, JSON_THROW_ON_ERROR);
    $catalog = json_decode(file_get_contents(resource_path('fiscal/catalogs/rtc-codes-2026.10.09-28-draft52.json')), true, 512, JSON_THROW_ON_ERROR);
    $input['policy'] = $catalog['dere_negative_base_local_settlement'];
    $input['known'] = new DateTimeImmutable($input['policy']['published_at']);
    $input['request'] = ['synthetic' => true, 'supplier_id' => '11111111000191', 'period' => '2026-09', 'prior_period' => '2026-08',
        'operation_hash' => CanonicalJson::hash($input['operation']), 'monthly_input_hash' => CanonicalJson::hash($input['month']),
        'gross_tax_hash' => CanonicalJson::hash($input['gross']), 'parameters' => $request['parameters'],
        'prior_closing_events' => $request['prior_closing_events']];
    $input['sources'] = resource_path('fiscal/sources/official');

    return $input;
}

/** @param array<string, mixed> $input @return array<string, mixed> */
function planSettlement52(array $input): array
{
    return (new DereFinancialNegativeBaseSettlementPlanner)->plan($input['operation'], $input['month'], $input['gross'],
        $input['known'], $input['rules'], $input['request'], $input['policy'], $input['sources']);
}

it('consumes the active prior version against gross-down bases and proposes separate IBS and CBS taxes', function (): void {
    $input = settlement52();
    $result = planSettlement52($input);
    expect($result['status'])->toBe('LOCAL_BCN_TAX_SETTLEMENT_PROPOSED')
        ->and($result['assessed_pools']['1010I']['deduction'])->toBe('50.00')
        ->and($result['assessed_pools']['1010I']['assessed_base'])->toBe('841.09')
        ->and($result['assessed_pools']['1010C']['deduction'])->toBe('10.00')
        ->and($result['assessed_pools']['1010C']['assessed_base'])->toBe('881.09')
        ->and($result['ibs_nominal_proposal'])->toBe('0.84')->and($result['cbs_nominal_proposal'])->toBe('7.93')
        ->and($result['original_gross_debits'])->toBe($input['gross'])
        ->and($result['original_gross_debits_hash'])->toBe(CanonicalJson::hash($input['gross']))
        ->and(array_column($result['stock_after_proposed_consumption'], 'available_after_proposed_consumption', 'codBCN'))->toBe([
            '1010I20260600' => '0.00', '1010C20260600' => '80.00', '1010C20260801' => '0.00'])
        ->and($result['eligible_for_posting'])->toBeFalse()->and($result['production_ready'])->toBeFalse();
});

it('treats an omitted option as no use for that root while preserving the submitted parameters', function (): void {
    $input = settlement52();
    $input['request']['parameters'] = ['infoBCN' => [['codBCNRaiz' => '1010I', 'usarBCNAcum' => '0']], 'detBCNeg' => []];
    $result = planSettlement52($input);
    expect($result['status'])->toBe('LOCAL_BCN_TAX_SETTLEMENT_PROPOSED')
        ->and($result['assessed_pools']['1010C']['option_omitted'])->toBeTrue()
        ->and($result['assessed_pools']['1010C']['deduction'])->toBe('0.00')
        ->and($result['cbs_nominal_proposal'])->toBe('8.02')
        ->and($result['consumption']['parameters_hash'])->toBe(CanonicalJson::hash($input['request']['parameters']));
});

it('preserves the2026 collection waiver independently of the changed nominal tax proposal', function (): void {
    $input = settlement52();
    $input['gross']['collection_status'] = 'DISPENSED_ART348_PARAGRAPH1';
    $input['request']['gross_tax_hash'] = CanonicalJson::hash($input['gross']);
    $result = planSettlement52($input);
    expect($result['ibs_nominal_proposal'])->toBe('0.84')
        ->and($result['collection_before_credit_offset_proposal'])->toBe(['IBS' => '0.00', 'CBS' => '0.00']);
});

it('requires source-bound request inputs', function (string $field, mixed $value, string $reason): void {
    $input = settlement52();
    $input['request'][$field] = $value;
    expect(planSettlement52($input)['reason'])->toBe($reason);
})->with([
    ['operation_hash', str_repeat('0', 64), 'RTC_DERE_BCN_SETTLEMENT_REQUEST_BINDING_OR_SCOPE_UNPROVEN'],
    ['monthly_input_hash', str_repeat('0', 64), 'RTC_DERE_BCN_SETTLEMENT_REQUEST_BINDING_OR_SCOPE_UNPROVEN'],
    ['gross_tax_hash', str_repeat('0', 64), 'RTC_DERE_BCN_SETTLEMENT_REQUEST_BINDING_OR_SCOPE_UNPROVEN'],
    ['synthetic', false, 'RTC_DERE_BCN_SETTLEMENT_REQUEST_BINDING_OR_SCOPE_UNPROVEN'],
    ['prior_period', '2026-07', 'RTC_DERE_BCN_SETTLEMENT_IMMEDIATELY_PRIOR_PERIOD_REQUIRED'],
    ['prior_closing_events', ['invalid' => []], 'RTC_DERE_BCN_SETTLEMENT_PRIOR_EVENT_LIST_REQUIRED'],
]);

it('does not borrow stock across forming roots', function (): void {
    $input = settlement52();
    $input['request']['parameters'] = ['infoBCN' => [['codBCNRaiz' => '1005I', 'usarBCNAcum' => '1', 'metodoAproveit' => '0']], 'detBCNeg' => []];
    expect(planSettlement52($input)['status'])->toBe('ACTION_REQUIRED');
});

it('does not use policy before publication or for a partially qualified competence', function (): void {
    $input = settlement52();
    $input['known'] = $input['known']->modify('-1 second');
    expect(planSettlement52($input)['reason'])->toBe('RTC_DERE_BCN_SETTLEMENT_POLICY_NOT_AVAILABLE');
    $input = settlement52();
    $input['policy']['valid_from'] = '2026-09-15';
    unset($input['policy']['source_hash']);
    $input['policy']['source_hash'] = CanonicalJson::hash($input['policy']);
    expect(planSettlement52($input)['reason'])->toBe('RTC_DERE_BCN_SETTLEMENT_POLICY_NOT_AVAILABLE');
});
