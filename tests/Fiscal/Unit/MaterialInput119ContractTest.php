<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\CollectionBasisReference119;
use App\Modules\Fiscal\ConsumptionReform\MaterialInputPolicy119;
use App\Modules\Fiscal\ConsumptionReform\OrdinaryAdjustmentInput119;

it('does not backdate source availability by truncating100ns capture precision', function (string $known, string $status): void {
    $r = (new MaterialInputPolicy119)->qualify(['material_input_policy119' => MaterialInputPolicy119::policy()], new DateTimeImmutable($known));
    expect($r['status'])->toBe($status)->and($r['legal_applicability_approved'])->toBeFalse()
        ->and($r['payment_proven'])->toBeFalse()->and($r['production_ready'])->toBeFalse();
})->with([['2026-10-20T12:49:04.592625+00:00', 'ACTION_REQUIRED'],
    ['2026-10-20T12:49:04.592626+00:00', 'RESOLVED'],
    ['2026-10-20T09:49:04.592626-03:00', 'RESOLVED']]);

it('does not permit a removed policy in a119 catalog', function (): void {
    expect(MaterialInputPolicy119::required(['version' => MaterialInputPolicy119::VERSION]))->toBeTrue()
        ->and(MaterialInputPolicy119::required(['version' => '2026.10.20-116-draft1']))->toBeFalse();
    $r = (new MaterialInputPolicy119)->qualify(['version' => MaterialInputPolicy119::VERSION], new DateTimeImmutable('2026-10-20T14:22:21+00:00'));
    expect($r['reason'])->toBe('RTC_INPUT119_SOURCE_POLICY_NOT_BOUND');
});

it('maps every recognized reference only to its declared method', function (string $declaredMethod, string $alias, string $actualMethod): void {
    $r = (new CollectionBasisReference119)->qualify($actualMethod, $alias);
    expect($r['status'])->toBe($actualMethod === $declaredMethod ? 'RESOLVED' : 'INVALID')
        ->and($r['payment_proven'])->toBeFalse()->and($r['legal_applicability_approved'])->toBeFalse();
})->with((function (): array {
    $rows = [];
    foreach (CollectionBasisReference119::references() as $method => $reference) {
        foreach ($reference['aliases'] as $alias) {
            foreach (['SUPPLIER', 'ACQUIRER', 'SPLIT_PAYMENT'] as $actual) {
                $rows[] = [$method, $alias, $actual];
            }
        }
    }

    return $rows;
})());

it('allows only the optional absent supplier reference and never invents a declaration', function (): void {
    $guard = new CollectionBasisReference119;
    expect($guard->qualify('SUPPLIER', null)['reference_declared'])->toBeFalse()
        ->and($guard->qualify('SUPPLIER', ' ')['status'])->toBe('ACTION_REQUIRED')
        ->and($guard->qualify('ACQUIRER', null)['status'])->toBe('ACTION_REQUIRED')
        ->and($guard->qualify('RECIPIENT', 'LC214 art. 36')['status'])->toBe('ACTION_REQUIRED');
});

it('refuses malformed nested container and rows without monetary conversion', function (mixed $rows): void {
    expect((new OrdinaryAdjustmentInput119)->qualify($rows)['status'])->not->toBe('RESOLVED');
})->with([[null], [false], ['bad'], [['row' => ['kind' => 'INCLUDED_CHARGE']]], [[false]], [[[]]]]);

it('requires unique trimmed line references within the same evidence context even when amounts differ', function (string $case): void {
    $a = ['kind' => 'INCLUDED_CHARGE', 'amount' => '10.00', 'evidence_id' => 'EV119'];
    $b = [...$a, 'amount' => '20.00', 'evidence_id' => ' EV119 '];
    if ($case === 'same-line') {
        $a['line_reference'] = 'L1';
        $b['line_reference'] = ' L1 ';
    }
    $r = (new OrdinaryAdjustmentInput119)->qualify([$a, $b]);
    expect($r['reason'])->toBe('RTC_ADJUSTMENT119_LINE_IDENTITY_AMBIGUOUS');
})->with(['no-line', 'same-line']);

it('refuses explicitly malformed line references', function (mixed $line): void {
    $r = (new OrdinaryAdjustmentInput119)->qualify([['kind' => 'INCLUDED_CHARGE', 'amount' => '1.00',
        'evidence_id' => 'EV119', 'line_reference' => $line]]);
    expect($r['reason'])->toBe('RTC_ADJUSTMENT119_LINE_REFERENCE_INVALID');
})->with([[''], [' '], [null], [false], [1], [[]]]);

it('does not mutate exact inputs or silently deduplicate two explicit lines', function (): void {
    $a = ['kind' => 'INCLUDED_CHARGE', 'amount' => 1, 'evidence_id' => 'EV119', 'line_reference' => '1'];
    $rows = [$a, [...$a, 'amount' => '1.00', 'line_reference' => '2']];
    $before = $rows;
    $r = (new OrdinaryAdjustmentInput119)->qualify($rows);
    expect($r['status'])->toBe('RESOLVED')->and($r['row_count'])->toBe(2)->and($rows)->toBe($before)
        ->and($r['evidence_authenticity_proven'])->toBeFalse();
});

it('does not use irrelevant injected fields to bypass line identity', function (): void {
    $a = ['kind' => 'INCLUDED_CHARGE', 'amount' => '10.00', 'evidence_id' => 'EV119', 'document_id' => 'A'];
    $b = [...$a, 'amount' => '20.00', 'document_id' => 'B', 'third_party_id' => 'INJECTED'];
    expect((new OrdinaryAdjustmentInput119)->qualify([$a, $b])['reason'])
        ->toBe('RTC_ADJUSTMENT119_LINE_IDENTITY_AMBIGUOUS');
});
