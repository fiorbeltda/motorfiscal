<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\PeriodRegimeAssessmentB40;

// B40 (4B.4.10): apuração periódica dos regimes de planos de saúde (arts. 234-238, 243) e prognósticos (arts. 244-247).

/** @return array<string, mixed> */
function b40Input(string $regime = 'HEALTH_PLAN', array $changes = []): array
{
    $lines = $regime === 'BETTING'
        ? [['id' => 'ARR', 'kind' => 'GROSS_COLLECTION', 'amount' => '10000.00', 'cash_date' => '2026-10-31', 'evidence_id' => 'E1'],
            ['id' => 'PRZ', 'kind' => 'PRIZES_PAID', 'amount' => '6000.00', 'cash_date' => '2026-10-31', 'evidence_id' => 'E2'],
            ['id' => 'DST', 'kind' => 'LEGAL_ALLOCATIONS', 'amount' => '1500.00', 'cash_date' => '2026-10-31', 'evidence_id' => 'E3']]
        : [['id' => 'PRM', 'kind' => 'PREMIUM_RECEIVED', 'amount' => '10000.00', 'cash_date' => '2026-10-05', 'evidence_id' => 'E1'],
            ['id' => 'FIN', 'kind' => 'RESERVE_FINANCIAL_INCOME', 'amount' => '500.00', 'cash_date' => '2026-10-31', 'evidence_id' => 'E2',
                'asset_liquidated_or_redeemed' => true, 'technical_provisions_reduced' => true, 'linked_to_premium_reserves' => true],
            ['id' => 'CLM', 'kind' => 'CLAIMS_PAID', 'amount' => '7000.00', 'cash_date' => '2026-10-20', 'evidence_id' => 'E3'],
            ['id' => 'BRK', 'kind' => 'INTERMEDIATION_PAID', 'amount' => '500.00', 'cash_date' => '2026-10-21', 'evidence_id' => 'E4']];

    return array_replace(['contract' => PeriodRegimeAssessmentB40::CONTRACT, 'currency' => 'BRL', 'regime' => $regime, 'period' => '2026-10',
        'source_kind' => 'SYNTHETIC_TEST', 'taxpayer_id' => 'FIXTURE.OPERATOR', 'evidence_id' => 'FIXTURE.PERIOD.BOOK',
        // 2026 test rates (CBS 0.9%, IBS 0.1%) as the reference input; the source label is a fixture, not an official act.
        'reference_rates' => ['cbs_percent' => '0.90', 'ibs_state_percent' => '0.10', 'ibs_municipal_percent' => '0.00',
            'source_id' => 'FIXTURE.TEST.RATES.2026', 'source_hash' => hash('sha256', 'fixture 2026 test rates')],
        'lines' => $lines], $changes);
}

it('assesses a health-plan period on cash basis with the 60% reduction of the reference rates', function (): void {
    $report = (new PeriodRegimeAssessmentB40)->assess(b40Input());
    expect($report['status'])->toBe('LOCAL_PERIOD_REGIME_ASSESSED')
        ->and($report['taxable_base'])->toBe('3000.00')
        ->and($report['taxes']['cbs']['applied_percent'])->toBe('0.3600')
        ->and($report['taxes']['cbs']['amount'])->toBe('10.80')
        ->and($report['taxes']['ibs_state']['amount'])->toBe('1.20')
        ->and($report['per_operation_document'])->toBe(['cst' => '820', 'cclasstrib' => '820001', 'nature' => 'INFORMATIONAL_TAX_DECLARED_IN_SPECIFIC_REGIME'])
        ->and($report['acquirer_credit'])->toBe('PROHIBITED')
        ->and((new PeriodRegimeAssessmentB40)->replay($report))->toBeTrue();
});

it('deducts ceded co-responsibility (art. 235, §2º)', function (): void {
    $input = b40Input();
    $input['lines'][] = ['id' => 'CED', 'kind' => 'CEDED_CORESPONSIBILITY', 'amount' => '1000.00', 'cash_date' => '2026-10-25', 'evidence_id' => 'E5'];
    expect((new PeriodRegimeAssessmentB40)->assess($input)['taxable_base'])->toBe('2000.00');
});

it('applies the 30% reduction to pet health plans and the full reference rates to betting', function (): void {
    $pet = (new PeriodRegimeAssessmentB40)->assess(b40Input('PET_HEALTH_PLAN'));
    expect($pet['taxes']['cbs']['applied_percent'])->toBe('0.6300')->and($pet['taxes']['cbs']['amount'])->toBe('18.90')
        ->and($pet['per_operation_document']['cclasstrib'])->toBe('820003');
    $bet = (new PeriodRegimeAssessmentB40)->assess(b40Input('BETTING'));
    expect($bet['taxable_base'])->toBe('2500.00')->and($bet['taxes']['cbs']['amount'])->toBe('22.50')
        ->and($bet['ibs_total'])->toBe('2.50')->and($bet['per_operation_document']['cclasstrib'])->toBe('820004');
});

it('refuses what the regime does not allow', function (array $changes, string $reason, string $regime = 'HEALTH_PLAN'): void {
    expect((new PeriodRegimeAssessmentB40)->assess(b40Input($regime, $changes))['reason'])->toBe($reason);
})->with([
    'cash outside the period' => [['lines' => [['id' => 'P', 'kind' => 'PREMIUM_RECEIVED', 'amount' => '1.00', 'cash_date' => '2026-11-01', 'evidence_id' => 'E']]], 'B40_LINE_OUTSIDE_PERIOD_CASH_BASIS'],
    'betting line in health plan' => [['lines' => [['id' => 'P', 'kind' => 'PRIZES_PAID', 'amount' => '1.00', 'cash_date' => '2026-10-01', 'evidence_id' => 'E']]], 'B40_LINE_KIND_NOT_IN_REGIME:PRIZES_PAID'],
    'negative base' => [['lines' => [['id' => 'C', 'kind' => 'CLAIMS_PAID', 'amount' => '1.00', 'cash_date' => '2026-10-01', 'evidence_id' => 'E']]], 'B40_NEGATIVE_PERIOD_BASE_TREATMENT_NOT_MATERIALIZED'],
    'reference rates without source' => [['reference_rates' => ['cbs_percent' => '0.90', 'ibs_state_percent' => '0.10', 'ibs_municipal_percent' => '0.00']], 'B40_REFERENCE_RATE_SOURCE_REQUIRED'],
    'financial income not liquidated' => [['lines' => [['id' => 'F', 'kind' => 'RESERVE_FINANCIAL_INCOME', 'amount' => '1.00', 'cash_date' => '2026-10-01', 'evidence_id' => 'E', 'asset_liquidated_or_redeemed' => true, 'technical_provisions_reduced' => false, 'linked_to_premium_reserves' => true]]], 'B40_FINANCIAL_INCOME_NOT_EFFECTIVELY_LIQUIDATED_OR_LINKED'],
    'line without evidence' => [['lines' => [['id' => 'P', 'kind' => 'PREMIUM_RECEIVED', 'amount' => '1.00', 'cash_date' => '2026-10-01']]], 'B40_LINE_IDENTITY_OR_EVIDENCE_INVALID'],
    'period outside 2026-2033' => [['period' => '2025-12'], 'B40_PERIOD_INVALID'],
    'inexact money' => [['lines' => [['id' => 'P', 'kind' => 'GROSS_COLLECTION', 'amount' => '1', 'cash_date' => '2026-10-01', 'evidence_id' => 'E']]], 'B40_LINE_AMOUNT_INVALID', 'BETTING'],
]);

it('rejects a tampered report on replay', function (): void {
    $report = (new PeriodRegimeAssessmentB40)->assess(b40Input());
    $report['taxes']['cbs']['amount'] = '0.01';
    expect((new PeriodRegimeAssessmentB40)->replay($report))->toBeFalse();
});
