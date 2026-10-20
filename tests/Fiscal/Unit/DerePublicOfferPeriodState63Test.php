<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DerePublicOfferPeriodStateQualifier;

it('qualifies complete contextual period histories and rejects invalid reopening proofs', function (string $name): void {
    $root = dirname(__DIR__, 3);
    $capture = json_decode((string) file_get_contents($root.'/storage/app/phase4b-dere9121-62-period-preparation-20261013/period-native-v2-result.json'), true, 512, JSON_THROW_ON_ERROR);
    $case = $capture['cases'][$name];
    $input = $case['input'];
    $result = (new DerePublicOfferPeriodStateQualifier)->qualify($input['period_events'], $input['public_events'],
        '11111111000191', '2026-09', new DateTimeImmutable('2026-10-13T13:16:56+00:00'), $root.'/resources/fiscal/sources/official', $input['history']);
    expect($result)->toBe($case['result'])->and($result['production_ready'])->toBeFalse();
    if ($case['expected_reason'] === null) {
        expect($result['period_state_locally_reconciled'])->toBeTrue()
            ->and($result['period_state_externally_verified'])->toBeFalse()
            ->and($result['closing_origin_economics_reconciled'])->toBeFalse()
            ->and($result['government_return_authenticated'])->toBeFalse();
    } else {
        expect($result['reason'])->toBe('RTC_DERE_PUBLIC_PERIOD_'.$case['expected_reason']);
    }
})->with(['initial-open', 'one-reopening', 'two-reopenings', 'closed', 'reopen-without-closing', 'reopening-sequence-reset',
    'wrong-origin-hash', 'wrong-return-id', 'return-error', 'real-context', 'history-hash', 'future-history',
    'public-outside-history', 'public-transition-overlap', 'stale-closing-receipt', 'missing-origin', 'wrong-supplier',
    'reopening-operation', 'reception-before-process', 'duplicate-close', 'history-ends-before-known', 'closed-after-public']);
