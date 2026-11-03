<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\CreditAdjustmentCycleB11;

function b11Credits(): array
{
    return [
        ['credit_id' => 'C-IBS-1', 'tax' => 'IBS', 'document_id' => 'NFSE-IN-1', 'amount' => '100.00', 'appropriated_on' => '2026-08-10'],
        ['credit_id' => 'C-CBS-1', 'tax' => 'CBS', 'document_id' => 'NFSE-IN-1', 'amount' => '900.00', 'appropriated_on' => '2026-08-10'],
        ['credit_id' => 'C-IBS-ZFM', 'tax' => 'IBS', 'document_id' => 'NFE-ZFM-7', 'amount' => '50.00', 'appropriated_on' => '2026-10-05', 'kind' => 'PRESUMED'],
        ['credit_id' => 'C-CBS-ASSET', 'tax' => 'CBS', 'document_id' => 'NFE-ASSET-3', 'amount' => '1200.00', 'appropriated_on' => '2026-09-01'],
    ];
}

function b11Event(array $override = []): array
{
    return ['event_id' => 'EV-1', 'credit_id' => 'C-IBS-1', 'tax' => 'IBS', 'document_id' => 'NFSE-IN-1', 'kind' => 'REVERSAL',
        'cause' => 'PERISHED', 'amount' => '40.00', 'event_date' => '2026-10-15', 'evidence_id' => 'LAUDO-PERECIMENTO-1', ...$override];
}

it('registers each legal cause in the registration period and keeps the remaining credit available', function (string $cause, string $kind, string $creditId, string $tax, string $document, string $basis): void {
    $event = b11Event(['cause' => $cause, 'kind' => $kind, 'credit_id' => $creditId, 'tax' => $tax, 'document_id' => $document, 'amount' => '10.00']);
    $result = (new CreditAdjustmentCycleB11)->apply(b11Credits(), [$event], '2026-10');
    $balance = array_column($result['balances'], null, 'credit_id')[$creditId];
    $appropriated = array_column(b11Credits(), 'amount', 'credit_id')[$creditId];
    expect($result['status'])->toBe('RESOLVED')
        ->and($result['registered'][0]['legal_basis'])->toBe($basis)
        ->and($result['registered'][0]['registration_period'])->toBe('2026-10')
        ->and($balance['available_for_consumption'])->toBe(bcsub($appropriated, '10.00', 2))
        ->and($result['period_adjustment_totals'][$tax])->toBe('10.00')
        ->and($result['payment_or_extinction_proven'])->toBeFalse()->and($result['legal_additions_computed'])->toBeFalse();
})->with([
    ['PERISHED', 'REVERSAL', 'C-IBS-1', 'IBS', 'NFSE-IN-1', 'LC214 art. 47, §6º'],
    ['DETERIORATED', 'REVERSAL', 'C-IBS-1', 'IBS', 'NFSE-IN-1', 'LC214 art. 47, §6º'],
    ['ROBBERY', 'REVERSAL', 'C-CBS-1', 'CBS', 'NFSE-IN-1', 'LC214 art. 47, §6º'],
    ['THEFT', 'REVERSAL', 'C-CBS-1', 'CBS', 'NFSE-IN-1', 'LC214 art. 47, §6º'],
    ['LOSS', 'REVERSAL', 'C-IBS-1', 'IBS', 'NFSE-IN-1', 'LC214 art. 47, §6º'],
    ['IMPROPER_APPROPRIATION', 'DISALLOWANCE', 'C-IBS-1', 'IBS', 'NFSE-IN-1', 'LC214 art. 45, §2º; art. 29, §§2º-4º'],
    ['PRESUMED_CREDIT_CONDITION_UNMET', 'DISALLOWANCE', 'C-IBS-ZFM', 'IBS', 'NFE-ZFM-7', 'LC214 art. 447, §2º; art. 465, §2º'],
]);

it('determines legal additions without computing them', function (array $override, string $period, string $status): void {
    $credits = array_values(array_filter(b11Credits(), fn (array $c): bool => substr($c['appropriated_on'], 0, 7) <= $period));
    $result = (new CreditAdjustmentCycleB11)->apply($credits, [b11Event($override)], $period);
    expect($result['registered'][0]['legal_additions']['status'])->toBe($status)
        ->and($result['registered'][0]['legal_additions']['computed'])->toBeFalse();
})->with([
    'disallowance of prior-period credit' => [['kind' => 'DISALLOWANCE', 'cause' => 'IMPROPER_APPROPRIATION'], '2026-10', 'LEGAL_ADDITIONS_REQUIRED'],
    'disallowance in appropriation period' => [['kind' => 'DISALLOWANCE', 'cause' => 'IMPROPER_APPROPRIATION', 'event_date' => '2026-08-20'], '2026-08', 'NOT_APPLICABLE'],
    'presumed credit condition unmet' => [['kind' => 'DISALLOWANCE', 'cause' => 'PRESUMED_CREDIT_CONDITION_UNMET', 'credit_id' => 'C-IBS-ZFM', 'document_id' => 'NFE-ZFM-7'], '2026-10', 'LEGAL_ADDITIONS_REQUIRED'],
    'reversal in event period' => [[], '2026-10', 'NOT_APPLICABLE'],
    'reversal registered late' => [['event_date' => '2026-09-20'], '2026-10', 'LATE_REGISTRATION_INTERPRETATION_PENDING'],
]);

it('applies the fixed-asset rule only with a regulation-sourced proportion', function (): void {
    $base = b11Event(['credit_id' => 'C-CBS-ASSET', 'tax' => 'CBS', 'document_id' => 'NFE-ASSET-3', 'cause' => 'FIXED_ASSET_ROBBERY_OR_THEFT', 'amount' => null]);
    $missing = (new CreditAdjustmentCycleB11)->apply(b11Credits(), [$base], '2026-10');
    $ok = (new CreditAdjustmentCycleB11)->apply(b11Credits(), [[...$base, 'regulation_proportion' => '0.75', 'regulation_source_id' => 'SYNTHETIC.TEST.REGULATION']], '2026-10');
    $over = (new CreditAdjustmentCycleB11)->apply(b11Credits(), [[...$base, 'regulation_proportion' => '1.5', 'regulation_source_id' => 'X']], '2026-10');
    expect($missing['reason'])->toBe('B11_FIXED_ASSET_REGULATION_PARAMETERS_REQUIRED')
        ->and($ok['registered'][0]['amount'])->toBe('900.00')
        ->and(array_column($ok['balances'], null, 'credit_id')['C-CBS-ASSET']['available_for_consumption'])->toBe('300.00')
        ->and($over['reason'])->toBe('B11_FIXED_ASSET_REGULATION_PARAMETERS_REQUIRED');
});

it('accumulates multiple events, refuses exceeding the remaining credit and keeps IBS and CBS independent', function (): void {
    $cycle = new CreditAdjustmentCycleB11;
    $events = [b11Event(['event_id' => 'EV-A', 'amount' => '60.00']), b11Event(['event_id' => 'EV-B', 'amount' => '40.00', 'cause' => 'LOSS'])];
    $full = $cycle->apply(b11Credits(), $events, '2026-10');
    $exceed = $cycle->apply(b11Credits(), [...$events, b11Event(['event_id' => 'EV-C', 'amount' => '0.01'])], '2026-10');
    $balances = array_column($full['balances'], null, 'credit_id');
    expect($balances['C-IBS-1']['available_for_consumption'])->toBe('0.00')
        ->and($balances['C-CBS-1']['available_for_consumption'])->toBe('900.00')
        ->and($full['period_adjustment_totals'])->toBe(['IBS' => '100.00', 'CBS' => '0.00'])
        ->and($exceed['reason'])->toBe('B11_ADJUSTMENT_EXCEEDS_REMAINING');
});

it('defers events dated after the registration period instead of registering them retroactively', function (): void {
    $result = (new CreditAdjustmentCycleB11)->apply(b11Credits(), [b11Event(['event_date' => '2026-11-02'])], '2026-10');
    expect($result['registered'])->toBe([])->and($result['deferred'][0]['reason'])->toBe('B11_EVENT_AFTER_REGISTRATION_PERIOD')
        ->and(array_column($result['balances'], null, 'credit_id')['C-IBS-1']['available_for_consumption'])->toBe('100.00');
});

it('is independent of event order and deterministic', function (): void {
    $events = [b11Event(['event_id' => 'EV-2', 'amount' => '10.00', 'event_date' => '2026-10-20']),
        b11Event(['event_id' => 'EV-1', 'amount' => '5.00', 'event_date' => '2026-10-02']),
        b11Event(['event_id' => 'EV-3', 'credit_id' => 'C-CBS-1', 'tax' => 'CBS', 'amount' => '1.00'])];
    $a = (new CreditAdjustmentCycleB11)->apply(b11Credits(), $events, '2026-10');
    $b = (new CreditAdjustmentCycleB11)->apply(array_reverse(b11Credits()), array_reverse($events), '2026-10');
    expect($a['result_hash'])->toBe($b['result_hash'])->and(array_column($a['registered'], 'event_id'))->toBe(['EV-1', 'EV-3', 'EV-2']);
});

it('refuses malformed, unlinked or unproven adjustments', function (array $override, string $reason, string $status): void {
    $result = (new CreditAdjustmentCycleB11)->apply(b11Credits(), [b11Event($override)], '2026-10');
    expect($result['status'])->toBe($status)->and($result['reason'])->toBe($reason)->and($result)->not->toHaveKey('balances');
})->with([
    'unknown cause' => [['cause' => 'FRAUD_SUSPECTED'], 'B11_CAUSE_NOT_IN_LEGAL_CATALOGUE', 'INVALID'],
    'kind does not match cause' => [['kind' => 'DISALLOWANCE'], 'B11_KIND_CAUSE_MISMATCH', 'INVALID'],
    'unknown credit' => [['credit_id' => 'C-404'], 'B11_CREDIT_NOT_FOUND', 'INVALID'],
    'tax mismatch' => [['tax' => 'CBS'], 'B11_CREDIT_LINK_CONFLICT', 'INVALID'],
    'document mismatch' => [['document_id' => 'NFSE-OUTRO'], 'B11_CREDIT_LINK_CONFLICT', 'INVALID'],
    'blank evidence' => [['evidence_id' => '  '], 'B11_EVIDENCE_REQUIRED', 'ACTION_REQUIRED'],
    'event before appropriation' => [['event_date' => '2026-08-01'], 'B11_EVENT_DATE_INVALID', 'INVALID'],
    'impossible date' => [['event_date' => '2026-02-30'], 'B11_EVENT_DATE_INVALID', 'INVALID'],
    'zero amount' => [['amount' => '0.00'], 'B11_AMOUNT_INVALID', 'INVALID'],
    'float-like amount' => [['amount' => '1e2'], 'B11_AMOUNT_INVALID', 'INVALID'],
    'three decimals' => [['amount' => '1.001'], 'B11_AMOUNT_INVALID', 'INVALID'],
    'exceeds credit' => [['amount' => '100.01'], 'B11_ADJUSTMENT_EXCEEDS_REMAINING', 'INVALID'],
    'presumed cause on ordinary credit' => [['kind' => 'DISALLOWANCE', 'cause' => 'PRESUMED_CREDIT_CONDITION_UNMET'], 'B11_PRESUMED_CAUSE_ON_NON_PRESUMED_CREDIT', 'INVALID'],
]);

it('refuses duplicate events, invalid credit records and invalid periods', function (): void {
    $cycle = new CreditAdjustmentCycleB11;
    expect($cycle->apply(b11Credits(), [b11Event(), b11Event(['amount' => '1.00'])], '2026-10')['reason'])->toBe('B11_EVENT_ID_INVALID_OR_DUPLICATE')
        ->and($cycle->apply([...b11Credits(), b11Credits()[0]], [], '2026-10')['reason'])->toBe('B11_CREDIT_RECORD_INVALID')
        ->and($cycle->apply([[...b11Credits()[0], 'document_id' => '']], [], '2026-10')['reason'])->toBe('B11_CREDIT_RECORD_INVALID')
        ->and($cycle->apply([[...b11Credits()[0], 'appropriated_on' => '2026-11-01']], [], '2026-10')['reason'])->toBe('B11_CREDIT_RECORD_INVALID')
        ->and($cycle->apply(b11Credits(), [], '2026-13')['reason'])->toBe('B11_PERIOD_INVALID');
});

it('matches an independent bcmath ledger oracle over a combination space', function (): void {
    $cycle = new CreditAdjustmentCycleB11;
    $checked = 0;
    foreach (['0.01', '0.99', '33.33', '50.00'] as $a) {
        foreach (['0.01', '16.67', '49.99', '50.00'] as $b) {
            $events = [b11Event(['event_id' => 'E1', 'amount' => $a]), b11Event(['event_id' => 'E2', 'amount' => $b, 'cause' => 'THEFT'])];
            $result = $cycle->apply(b11Credits(), $events, '2026-10');
            $remaining = bcsub(bcsub('100.00', $a, 2), $b, 2);
            if (bccomp($remaining, '0', 2) < 0) {
                expect($result['reason'])->toBe('B11_ADJUSTMENT_EXCEEDS_REMAINING');
            } else {
                expect(array_column($result['balances'], null, 'credit_id')['C-IBS-1']['available_for_consumption'])->toBe($remaining)
                    ->and($result['period_adjustment_totals']['IBS'])->toBe(bcadd($a, $b, 2));
            }
            $checked++;
        }
    }
    expect($checked)->toBe(16);
});
