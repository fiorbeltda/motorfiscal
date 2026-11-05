<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;

require_once dirname(__DIR__, 3).'/scripts/agent_a/arithmetic156_fixture.php';

it('keeps three native monetary components separate with a nonzero municipal test rate', function (string $profile, int $year): void {
    [$input, $knowledge] = arithmetic156($profile, $year);
    $result = evaluate156($input, $knowledge);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['official'])->toBeFalse()
        ->and($result['decision'])->toBe([])->and($result['reason_codes'])->toContain('RTC_SYNTHETIC_TEST_ONLY');
    $trace = array_column($result['trace'], null, 'stage');
    $candidate = $trace['SYNTHETIC_ARITHMETIC156'];
    if ($profile === 'import-service') {
        expect($trace)->toHaveKey('IMPORT_NOMINAL_CALCULATION');
        $amounts = $trace['IMPORT_NOMINAL_CALCULATION'];
        expect(DecimalValue::fromString($amounts['ibs_municipal'])->compareTo(DecimalValue::fromString('0')))->toBeGreaterThan(0);

        return;
    }
    expect($candidate['candidate_status'])->toBe('VALID');
    $decision = $candidate['candidate_decision'];
    $base = DecimalValue::fromString($decision['base']['taxable']);
    foreach ([['ibs', 'state_amount', 'state_rate_percent'], ['ibs', 'municipal_amount', 'municipal_rate_percent'],
        ['cbs', 'nominal_amount', 'federal_rate_percent']] as [$tax, $amount, $rate]) {
        $expected = $base->multipliedBy(DecimalValue::fromString($decision[$tax][$rate]))->dividedBy(DecimalValue::fromString('100'), 10)->rounded(2)->value();
        expect($decision[$tax][$amount])->toBe($expected);
    }
    expect($decision['ibs']['nominal_amount'])->toBe(DecimalValue::fromString($decision['ibs']['state_amount'])->plus(DecimalValue::fromString($decision['ibs']['municipal_amount']))->value())
        ->and(DecimalValue::fromString($decision['ibs']['municipal_amount'])->compareTo(DecimalValue::fromString('0')))->toBeGreaterThan(0);
})->with(array_keys(profiles156()))->with([2026, 2027, 2030, 2033]);

it('never selects synthetic test rates as official and requires both exact evidence gates', function (): void {
    [$input, $knowledge] = arithmetic156('saas', 2026);
    $rates = $knowledge['documents']['rtc_rate_catalog'];
    unset($rates[TemporalRateCatalog::EVIDENCE_KEY]);
    $result = (new TemporalRateCatalog($rates))->resolve('IBS_MUNICIPAL', 'BR', LocalDate::fromString('2026-10-01'), new DateTimeImmutable('2026-11-05'));
    expect($result['status'])->toBe('NOT_FOUND')->and($result['official'])->toBeFalse();
    unset($knowledge['documents']['rtc_code_catalog'][TemporalRateCatalog::EVIDENCE_KEY]);
    expect(evaluate156($input, $knowledge)['status'])->toBe('INVALID');
});
