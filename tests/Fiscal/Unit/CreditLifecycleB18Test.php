<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\CreditAdjustmentCycleB11;
use App\Modules\Fiscal\ConsumptionReform\CreditUtilizationPlannerB14;
use App\Modules\Fiscal\ConsumptionReform\PresumedCreditCatalogB17;

// B18 (4B.5.2/4B.8.11): end-to-end local credit lifecycle — appropriation, adjustment (B11), presumed
// credit (B17) and utilization (B14) — with value-conservation invariants. No extinction is claimed.

it('carries appropriated credits through reversal and utilization with exact conservation', function (): void {
    $credits = [
        ['credit_id' => 'C-IBS', 'tax' => 'IBS', 'document_id' => 'NFE-10', 'amount' => '500.00', 'appropriated_on' => '2026-09-15'],
        ['credit_id' => 'C-CBS', 'tax' => 'CBS', 'document_id' => 'NFE-10', 'amount' => '4500.00', 'appropriated_on' => '2026-09-15'],
    ];
    $events = [['event_id' => 'EV-ROUBO', 'credit_id' => 'C-IBS', 'tax' => 'IBS', 'document_id' => 'NFE-10', 'kind' => 'REVERSAL',
        'cause' => 'ROBBERY', 'amount' => '120.00', 'event_date' => '2026-10-03', 'evidence_id' => 'BO-123']];
    $cycle = (new CreditAdjustmentCycleB11)->apply($credits, $events, '2026-10');
    $balances = array_column($cycle['balances'], null, 'credit_id');
    $planned = (new CreditUtilizationPlannerB14)->plan([
        ['credit_id' => 'C-IBS', 'tax' => 'IBS', 'amount' => $balances['C-IBS']['available_for_consumption'], 'appropriated_period' => '2026-09'],
        ['credit_id' => 'C-CBS', 'tax' => 'CBS', 'amount' => $balances['C-CBS']['available_for_consumption'], 'appropriated_period' => '2026-09'],
    ], [
        ['debt_id' => 'D-IBS', 'tax' => 'IBS', 'amount' => '300.00', 'period' => '2026-10', 'fact_date' => '2026-10-10'],
        ['debt_id' => 'D-CBS', 'tax' => 'CBS', 'amount' => '5000.00', 'period' => '2026-10', 'fact_date' => '2026-10-10'],
    ], '2026-10');
    $used = array_column(array_map(fn ($c) => [$c['credit_id'], $c['amount']], $planned['compensations']), 1, 0);
    $left = array_column($planned['remaining_credits'], 'remaining', 'credit_id');
    expect($balances['C-IBS']['available_for_consumption'])->toBe('380.00')
        ->and(bcadd(bcadd($used['C-IBS'], $left['C-IBS'], 2), '120.00', 2))->toBe('500.00')
        ->and(bcadd($used['C-CBS'], $left['C-CBS'], 2))->toBe('4500.00')
        ->and(array_column($planned['remaining_debts'], 'remaining', 'debt_id'))->toEqual(['D-IBS' => '0.00', 'D-CBS' => '500.00'])
        ->and($planned['debts_extinguished'])->toBeFalse()->and($cycle['payment_or_extinction_proven'])->toBeFalse();
});

it('keeps ZFM presumed credit usable only for compensation, never for refund (art. 452)', function (): void {
    $presumed = (new PresumedCreditCatalogB17)->qualify(['hypothesis' => 'ZFM_NATIONAL_INDUSTRIALIZED_GOOD', 'acquirer_regime' => 'REGULAR',
        'base_amount' => '2000.00', 'acquisition_date' => '2027-03-01', 'document_id' => 'NFE-ZFM', 'habilitated' => true,
        'zero_rate_operation' => true, 'habilitation_evidence_id' => 'SUFRAMA-9', 'origin_region' => 'SOUTH_SOUTHEAST_EXCEPT_ES']);
    $planned = (new CreditUtilizationPlannerB14)->plan([
        ['credit_id' => 'P-ZFM', 'tax' => 'IBS', 'amount' => $presumed['amounts']['IBS'], 'appropriated_period' => '2027-03', 'refund_prohibited' => true],
        ['credit_id' => 'C-ORD', 'tax' => 'IBS', 'amount' => '50.00', 'appropriated_period' => '2027-03'],
    ], [['debt_id' => 'D', 'tax' => 'IBS', 'amount' => '40.00', 'period' => '2027-03', 'fact_date' => '2027-03-20']], '2027-03', true);
    expect($presumed['amounts']['IBS'])->toBe('150.00')
        ->and(array_map(fn ($r) => [$r['credit_id'], $r['amount']], $planned['refund']))->toBe([['C-ORD', '10.00']])
        ->and(array_column($planned['remaining_credits'], null, 'credit_id')['P-ZFM'])->toMatchArray(['remaining' => '150.00', 'refund_prohibited' => true]);
});

it('refuses to consume more credit than appropriated across the chain (invariant)', function (): void {
    foreach (['0.01', '100.00', '499.99', '500.00'] as $reversal) {
        $cycle = (new CreditAdjustmentCycleB11)->apply(
            [['credit_id' => 'C', 'tax' => 'CBS', 'document_id' => 'D', 'amount' => '500.00', 'appropriated_on' => '2026-08-01']],
            [['event_id' => 'E', 'credit_id' => 'C', 'tax' => 'CBS', 'document_id' => 'D', 'kind' => 'REVERSAL', 'cause' => 'LOSS',
                'amount' => $reversal, 'event_date' => '2026-10-01', 'evidence_id' => 'EV']], '2026-10');
        $available = $cycle['balances'][0]['available_for_consumption'];
        $planned = (new CreditUtilizationPlannerB14)->plan([['credit_id' => 'C', 'tax' => 'CBS', 'amount' => $available, 'appropriated_period' => '2026-08']],
            [['debt_id' => 'X', 'tax' => 'CBS', 'amount' => '10000.00', 'period' => '2026-10', 'fact_date' => '2026-10-02']], '2026-10');
        $total = '0.00';
        foreach ($planned['compensations'] as $c) {
            $total = bcadd($total, $c['amount'], 2);
        }
        expect(bccomp(bcadd($total, $reversal, 2), '500.00', 2))->toBe(0);
    }
});
