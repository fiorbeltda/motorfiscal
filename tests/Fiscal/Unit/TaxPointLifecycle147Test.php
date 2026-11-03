<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\TaxPointContext86;

require_once dirname(__DIR__, 3).'/scripts/agent_a/temporal147_fixture.php';

it('compares lifecycle instants at full supported precision without changing the source policy', function (string $key, string $cut, string $known, string $status): void {
    $policy = temporal147Policy();
    $result = (new TaxPointContext86)->policyAvailability(new DateTimeImmutable($known), [...$policy, $key => $cut]);
    expect($result['status'])->toBe($status);
    if ($status === 'RESOLVED') {
        expect($result['source_hashes'])->toBe($policy['source_hashes']);
    } else {
        expect($result['reason'])->toBe('RTC_TAX_POINT_POLICY_NO_LONGER_AVAILABLE');
    }
})->with((function (): array {
    $rows = [];
    foreach (['revoked_at', 'superseded_at'] as $key) {
        foreach ([
            ['2026-10-15T23:00:00+00:00', '2026-10-15T22:59:59.999999+00:00', 'RESOLVED'],
            ['2026-10-15T23:00:00+00:00', '2026-10-15T23:00:00+00:00', 'ACTION_REQUIRED'],
            ['2026-10-15T23:00:00.000001+00:00', '2026-10-15T23:00:00+00:00', 'RESOLVED'],
            ['2026-10-15T23:00:00.000001+00:00', '2026-10-15T23:00:00.000001+00:00', 'ACTION_REQUIRED'],
            ['2026-10-15T23:00:00.000001+00:00', '2026-10-15T23:00:00.000002+00:00', 'ACTION_REQUIRED'],
            ['2026-10-15T23:00:00.1+00:00', '2026-10-15T23:00:00.099999+00:00', 'RESOLVED'],
            ['2026-10-15T23:00:00.1+00:00', '2026-10-15T23:00:00.100000+00:00', 'ACTION_REQUIRED'],
            ['2026-10-15T20:00:00.000001-03:00', '2026-10-15T23:00:00.000001+00:00', 'ACTION_REQUIRED'],
            ['2026-10-15T23:00:00.000001Z', '2026-10-15T23:00:00+00:00', 'RESOLVED'],
            ['2026-10-15T23:00:00Z', '2026-10-15T23:00:00+00:00', 'ACTION_REQUIRED'],
        ] as $case) {
            $rows[] = [$key, ...$case];
        }
    }

    return $rows;
})());

it('rejects impossible or ambiguous lifecycle data without truncating precision', function (mixed $value): void {
    foreach (['revoked_at', 'superseded_at'] as $key) {
        $result = (new TaxPointContext86)->policyAvailability(new DateTimeImmutable('2026-10-15T23:00:00Z'),
            [...temporal147Policy(), $key => $value]);
        expect($result['status'])->toBe('ACTION_REQUIRED')
            ->and($result['reason'])->toBe('RTC_TAX_POINT_POLICY_LIFECYCLE_INVALID');
    }
})->with([
    ['2026-02-30T23:00:00+00:00'], ['2026-10-15T23:00:00.1234567+00:00'],
    ['2026-10-15T23:00:00'], ['2026-10-15 23:00:00+00:00'], ['2026-10-15T23:00:00+24:00'],
    ['2026-10-15T23:00:00-00:00'], ['2026-10-15T23:00:00.000001Z '],
    ["2026-10-15T23:00:00+00:00\0"], [true], [0], [[]], [''],
]);

it('preserves absent lifecycle values and the original source observation boundary', function (): void {
    $policy = temporal147Policy();
    $context = new TaxPointContext86;
    expect($context->policyAvailability(new DateTimeImmutable('2026-10-15T14:11:36.200606Z'), $policy)['reason'])
        ->toBe('RTC_TAX_POINT_POLICY_NOT_YET_AVAILABLE')
        ->and($context->policyAvailability(new DateTimeImmutable('2026-10-15T14:11:36.200607Z'), $policy)['status'])->toBe('RESOLVED')
        ->and($context->policyAvailability(new DateTimeImmutable('2026-10-15T23:00:00Z'),
            [...$policy, 'revoked_at' => null, 'superseded_at' => null])['status'])->toBe('RESOLVED');
});
