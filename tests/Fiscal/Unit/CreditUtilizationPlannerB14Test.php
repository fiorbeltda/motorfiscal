<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\CreditUtilizationPlannerB14;

function b14Credit(string $id, string $tax, string $amount, string $period, array $extra = []): array
{
    return ['credit_id' => $id, 'tax' => $tax, 'amount' => $amount, 'appropriated_period' => $period, ...$extra];
}

function b14Debt(string $id, string $tax, string $amount, string $period, string $date, array $extra = []): array
{
    return ['debt_id' => $id, 'tax' => $tax, 'amount' => $amount, 'period' => $period, 'fact_date' => $date, ...$extra];
}

it('follows the art. 53 order: overdue prior balance, then current debts chronologically, then subsequent', function (): void {
    $result = (new CreditUtilizationPlannerB14)->plan(
        [b14Credit('C1', 'IBS', '150.00', '2026-09')],
        [b14Debt('D-NEXT', 'IBS', '40.00', '2026-11', '2026-11-03'), b14Debt('D-CUR-2', 'IBS', '30.00', '2026-10', '2026-10-20'),
            b14Debt('D-OLD', 'IBS', '50.00', '2026-08', '2026-08-10', ['overdue' => true]), b14Debt('D-CUR-1', 'IBS', '60.00', '2026-10', '2026-10-05')],
        '2026-10');
    expect(array_column($result['compensations'], 'debt_id'))->toBe(['D-OLD', 'D-CUR-1', 'D-CUR-2', 'D-NEXT'])
        ->and(array_column($result['compensations'], 'amount'))->toBe(['50.00', '60.00', '30.00', '10.00'])
        ->and(array_column($result['compensations'], 'basis'))->toBe(['LC214 art. 53, I', 'LC214 art. 53, II', 'LC214 art. 53, II', 'LC214 art. 53, III'])
        ->and(array_column($result['remaining_debts'], 'remaining', 'debt_id')['D-NEXT'])->toBe('30.00')
        ->and($result['debts_extinguished'])->toBeFalse()->and($result['official_compensation_proven'])->toBeFalse();
});

it('never compensates IBS credit with CBS debt or vice versa (art. 47, §1º, I)', function (): void {
    $result = (new CreditUtilizationPlannerB14)->plan([b14Credit('C-IBS', 'IBS', '100.00', '2026-10')],
        [b14Debt('D-CBS', 'CBS', '80.00', '2026-10', '2026-10-01')], '2026-10');
    expect($result['compensations'])->toBe([])->and($result['remaining_debts'][0]['remaining'])->toBe('80.00')
        ->and($result['remaining_credits'][0]['remaining'])->toBe('100.00')->and($result['cross_tax_compensation'])->toBeFalse();
});

it('expires credits five years after the first day of the period following appropriation (art. 54)', function (string $period, bool $expired): void {
    $result = (new CreditUtilizationPlannerB14)->plan([b14Credit('C-OLD', 'CBS', '10.00', '2021-10')], [], $period);
    expect($result['expired_credits'] !== [])->toBe($expired);
    if ($expired) {
        expect($result['expired_credits'][0]['expired_on'])->toBe('2026-11-01');
    }
})->with([['2026-10', false], ['2026-11', true], ['2026-12', true]]);

it('preserves the original appropriation date for succession and refuses other transfers (art. 55)', function (): void {
    $planner = new CreditUtilizationPlannerB14;
    $succession = $planner->plan([b14Credit('C-SUC', 'IBS', '20.00', '2021-11', ['origin' => 'SUCCESSION', 'predecessor_id' => '11111111000191'])],
        [], '2026-12', transfers: [['reason' => 'INCORPORATION']]);
    expect($succession['expired_credits'][0]['expired_on'])->toBe('2026-12-01')
        ->and($succession['succession_transfers_allowed'])->toBe(['INCORPORATION'])
        ->and($planner->plan([], [], '2026-10', transfers: [['reason' => 'SALE']])['reason'])->toBe('B14_CREDIT_TRANSFER_PROHIBITED_ART55')
        ->and($planner->plan([b14Credit('C', 'IBS', '1.00', '2026-10', ['origin' => 'SUCCESSION'])], [], '2026-10')['reason'])->toBe('B14_SUCCESSION_PREDECESSOR_REQUIRED');
});

it('reserves remaining credit for a refund request instead of subsequent-period compensation (art. 53, §1º)', function (): void {
    $result = (new CreditUtilizationPlannerB14)->plan([b14Credit('C1', 'IBS', '100.00', '2026-10')],
        [b14Debt('D-CUR', 'IBS', '30.00', '2026-10', '2026-10-01'), b14Debt('D-NEXT', 'IBS', '50.00', '2026-11', '2026-11-01')], '2026-10', true);
    expect(array_column($result['compensations'], 'debt_id'))->toBe(['D-CUR'])
        ->and($result['refund'][0])->toMatchArray(['amount' => '70.00', 'status' => 'REFUND_REQUEST_NOT_PROCESSED']);
});

it('excludes active-debt-roll and non-overdue prior debts, and uses the oldest credit first', function (): void {
    $result = (new CreditUtilizationPlannerB14)->plan(
        [b14Credit('C-NEW', 'CBS', '50.00', '2026-10'), b14Credit('C-OLD', 'CBS', '50.00', '2026-01')],
        [b14Debt('D-DA', 'CBS', '10.00', '2026-05', '2026-05-01', ['overdue' => true, 'active_debt_roll' => true]),
            b14Debt('D-PRIOR', 'CBS', '10.00', '2026-06', '2026-06-01'), b14Debt('D-CUR', 'CBS', '60.00', '2026-10', '2026-10-01')], '2026-10');
    expect(array_column($result['excluded_debts'], 'reason', 'debt_id'))->toBe(['D-DA' => 'B14_ACTIVE_DEBT_ROLL_NOT_COMPENSABLE_ART53_I', 'D-PRIOR' => 'B14_PRIOR_DEBT_NOT_OVERDUE_STATUS_REQUIRED'])
        ->and(array_map(fn ($c) => [$c['credit_id'], $c['amount']], $result['compensations']))->toBe([['C-OLD', '50.00'], ['C-NEW', '10.00']]);
});

it('refuses monetary correction, malformed inputs and future credits', function (array $credits, array $debts, string $reason): void {
    expect((new CreditUtilizationPlannerB14)->plan($credits, $debts, '2026-10')['reason'])->toBe($reason);
})->with([
    'correction' => [[b14Credit('C', 'IBS', '10.00', '2026-01', ['monetary_correction' => '1.05'])], [], 'B14_MONETARY_CORRECTION_PROHIBITED_ART53_2'],
    'float amount' => [[b14Credit('C', 'IBS', '1e3', '2026-01')], [], 'B14_CREDIT_INVALID'],
    'unknown tax' => [[b14Credit('C', 'ISS', '10.00', '2026-01')], [], 'B14_CREDIT_INVALID'],
    'future credit' => [[b14Credit('C', 'IBS', '10.00', '2026-11')], [], 'B14_CREDIT_FROM_FUTURE_PERIOD'],
    'duplicate credit' => [[b14Credit('C', 'IBS', '1.00', '2026-01'), b14Credit('C', 'IBS', '1.00', '2026-01')], [], 'B14_CREDIT_INVALID'],
    'debt without date' => [[], [['debt_id' => 'D', 'tax' => 'IBS', 'amount' => '1.00', 'period' => '2026-10']], 'B14_DEBT_INVALID'],
]);

it('conserves value: compensated plus remaining equals inputs per tax (bcmath oracle) and is order-independent', function (): void {
    $credits = [b14Credit('A', 'IBS', '33.33', '2026-03'), b14Credit('B', 'IBS', '66.67', '2026-07'), b14Credit('C', 'CBS', '12.34', '2026-02')];
    $debts = [b14Debt('X', 'IBS', '45.00', '2026-10', '2026-10-09'), b14Debt('Y', 'IBS', '80.00', '2026-12', '2026-12-01'), b14Debt('Z', 'CBS', '5.55', '2026-10', '2026-10-01')];
    $planner = new CreditUtilizationPlannerB14;
    $a = $planner->plan($credits, $debts, '2026-10');
    $b = $planner->plan(array_reverse($credits), array_reverse($debts), '2026-10');
    expect($a['result_hash'])->toBe($b['result_hash']);
    foreach (['IBS', 'CBS'] as $tax) {
        $used = '0';
        foreach ($a['compensations'] as $c) {
            $used = $c['tax'] === $tax ? bcadd($used, $c['amount'], 2) : $used;
        }
        $left = '0';
        foreach ($a['remaining_credits'] as $c) {
            $left = $c['tax'] === $tax ? bcadd($left, $c['remaining'], 2) : $left;
        }
        $in = '0';
        foreach ($credits as $c) {
            $in = $c['tax'] === $tax ? bcadd($in, $c['amount'], 2) : $in;
        }
        expect(bcadd($used, $left, 2))->toBe(bcadd($in, '0', 2));
    }
});
