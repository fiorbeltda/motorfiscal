<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\AdvanceTaxReconciler84;
use App\Modules\Fiscal\ConsumptionReform\SupplyDateEvidenceResolver83;
use App\Modules\Fiscal\ConsumptionReform\TaxPointEventPlanner85;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;

/** @return array<int, array<string, mixed>> */
function temporal86Cases(string $name): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 3).'/storage/app/phase4b-temporal86-integration-preparation-20261015/'.$name.'-cases.json'), true, 512, JSON_THROW_ON_ERROR);
}

it('retains the staged event planner cases under the installed namespace', function (array $case): void {
    $result = (new TaxPointEventPlanner85)->plan($case['operation'], new DateTimeImmutable($case['known_at']));
    expect($result['status'])->toBe($case['status'])->and($result['production_ready'])->toBeFalse();
    if ($case['reason'] !== null) {
        expect($result['reason'])->toBe($case['reason']);
    }
    if ($case['dates'] !== null) {
        expect(array_map(static fn (array $row): array => [$row['event_date'], $row['rate_date']], $result['events']))->toBe($case['dates']);
    }
})->with(fn (): array => array_map(static fn (array $row): array => [$row], temporal86Cases('planner')));

it('retains documentary presumptions and contrary evidence without claiming remote authentication', function (array $case): void {
    $result = (new SupplyDateEvidenceResolver83)->resolve($case['operation'], new DateTimeImmutable($case['known_at']));
    expect($result['status'])->toBe($case['expected_status'])->and($result['production_ready'])->toBeFalse()
        ->and($result['source_policy_qualified'])->toBeFalse();
    if ($case['expected_reason'] !== null) {
        expect($result['reason'])->toBe($case['expected_reason']);
    } else {
        expect($result['basis'])->toBe($case['expected_basis'])->and($result['supply_event']['date'])->toBe($case['expected_date']);
    }
})->with(fn (): array => array_map(static fn (array $row): array => [$row], temporal86Cases('presumption')));

it('retains exact advance reconciliation against the independent decimal oracle', function (array $case): void {
    $result = (new AdvanceTaxReconciler84(new TemporalRateCatalog($case['catalog'])))
        ->reconcile($case['plan'], 'SP', '3550308', new DateTimeImmutable('2027-01-10T23:00:00+00:00'));
    expect($result['production_ready'])->toBeFalse()->and($result['posting_authorized'])->toBeFalse()
        ->and($result['credit_appropriation_authorized'])->toBeFalse();
    if ($case['reason'] !== null) {
        expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe($case['reason']);
    } else {
        expect($result['status'])->toBe('LOCAL_ADVANCE_RECONCILIATION_ONLY')->and($result['prior_advance_debits_proven'])->toBeFalse();
        foreach ($case['expected'] as $tax => $values) {
            foreach ($values as $key => $value) {
                expect($result['taxes'][$tax][$key])->toBe($value);
            }
        }
    }
})->with(fn (): array => array_map(static fn (array $row): array => [$row], temporal86Cases('reconciliation')));
