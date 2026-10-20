<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\AdvanceOnlyNominal87;
use App\Modules\Fiscal\ConsumptionReform\AdvanceOnlyOrigin87;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;

/** @return array<string, mixed> */
function advance88Fixture(): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 3).'/storage/app/phase4b-advance88-preparation-20261015/advance-origin-cases.json'), true, 512, JSON_THROW_ON_ERROR);
}

it('preserves origin, distinct event and rate dates and the independent decimal oracle', function (array $case): void {
    $known = new DateTimeImmutable($case['known_at']);
    $origin = (new AdvanceOnlyOrigin87)->select($case['context'], $case['selection'], $known);
    $nominal = (new AdvanceOnlyNominal87(new TemporalRateCatalog(advance88Fixture()['rate_catalog'])))
        ->calculate($case['context'], $case['selection'], 'SP', '3550308', $known);
    expect($origin['production_ready'])->toBeFalse()->and($nominal['production_ready'])->toBeFalse()
        ->and($nominal['posting_authorized'])->toBeFalse()->and($nominal['credit_appropriation_authorized'])->toBeFalse();
    if ($case['origin_reason'] !== null) {
        expect($origin['status'])->toBe('ACTION_REQUIRED')->and($origin['reason'])->toBe($case['origin_reason'])
            ->and($nominal['reason'])->toBe($case['origin_reason']);

        return;
    }
    expect($origin['status'])->toBe('LOCAL_ADVANCE_ORIGIN_ONLY');
    if ($case['nominal_reason'] !== null) {
        expect($nominal['status'])->toBe('ACTION_REQUIRED')->and($nominal['reason'])->toBe($case['nominal_reason']);

        return;
    }
    expect($nominal['status'])->toBe('LOCAL_ADVANCE_NOMINAL_ONLY')->and($nominal['registration_proven'])->toBeFalse()
        ->and($nominal['extinction_proven'])->toBeFalse()->and($nominal['cash_relief_qualified'])->toBeFalse();
    foreach (['event_date', 'rate_date', 'base_amount'] as $field) {
        expect($origin['event'][$field])->toBe($case['expected'][$field]);
    }
    foreach ($case['expected']['taxes'] as $tax => $amount) {
        expect($nominal['taxes'][$tax]['nominal_amount'])->toBe($amount)
            ->and($nominal['taxes'][$tax]['cash_due'])->toBeNull();
    }
    $reversed = $case['context'];
    $reversed['paid_installments'] = array_reverse($reversed['paid_installments']);
    expect((new AdvanceOnlyOrigin87)->select($reversed, $case['selection'], $known))->toBe($origin);
})->with(function (): array {
    $cases = [];
    foreach (advance88Fixture()['cases'] as $case) {
        $cases[$case['name']] = [$case];
    }

    return $cases;
});
