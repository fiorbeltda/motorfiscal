<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3).'/scripts/agent_a/locality150_fixture.php';

use App\Modules\Fiscal\Core\Shared\CanonicalJson;

it('selects the municipality with the largest combined property area rather than the largest single parcel', function (): void {
    $report = locality149Qualifier()->qualify(locality150Fixture());
    expect($report['status'])->toBe('LOCAL_TERRITORIAL_LOCATION_QUALIFIED')
        ->and($report['locality'])->toBe(['uf' => 'SP', 'municipality_ibge' => '3550308'])
        ->and($report['total_measure'])->toBe('100.00')
        ->and($report['jurisdiction_allocations'])->toHaveCount(1)
        ->and($report['jurisdiction_allocations'][0]['measure'])->toBe('60.50')
        ->and($report['jurisdiction_allocations'][0]['share'])->toBe(['numerator' => '1', 'denominator' => '1'])
        ->and($report['tax_rate_selected'])->toBeFalse()
        ->and($report['historical_juridical_validity_proven'])->toBeFalse()
        ->and(locality149Qualifier()->replay($report))->toBeTrue();
});

it('retains exact non-terminating road shares and aggregates state and municipal weights separately', function (): void {
    $report = locality149Qualifier()->qualify(locality150Fixture('road'));
    expect($report['status'])->toBe('LOCAL_TERRITORIAL_LOCATION_QUALIFIED')->and($report['locality'])->toBeNull()
        ->and($report['jurisdiction_allocations'])->toHaveCount(3)->and($report['state_allocations'])->toHaveCount(2);
    foreach ($report['jurisdiction_allocations'] as $allocation) {
        expect($allocation['share'])->toBe(['numerator' => '1', 'denominator' => '3']);
    }
    $states = array_column($report['state_allocations'], null, 'uf');
    expect($states['SP']['share'])->toBe(['numerator' => '2', 'denominator' => '3'])
        ->and($states['RJ']['share'])->toBe(['numerator' => '1', 'denominator' => '3'])
        ->and($report['allocation_semantics'])->toBe('SAME_SUPPLY_JURISDICTION_WEIGHTS_NOT_ADDITIONAL_SUPPLIES')
        ->and(locality149Qualifier()->replay($report))->toBeTrue();
});

it('compares areas exactly beyond binary floating point and does not round a small difference into a tie', function (): void {
    $input = locality150Measures(locality150Fixture(), ['1.00000000000000000001', '1', '2.00000000000000000000'], '4.00000000000000000001');
    $report = locality149Qualifier()->qualify($input);
    expect($report['status'])->toBe('LOCAL_TERRITORIAL_LOCATION_QUALIFIED')
        ->and($report['locality']['municipality_ibge'])->toBe('3550308')
        ->and($report['total_measure'])->toBe('4.00000000000000000001');
});

it('does not use source row order as a property tie breaker', function (): void {
    $input = locality150Measures(locality150Fixture(), ['25', '25', '50'], '100');
    $first = locality149Qualifier()->qualify($input);
    $input['locations'] = array_reverse($input['locations']);
    $second = locality149Qualifier()->qualify($input);
    expect($first['reason'])->toBe('LOCALITY150_PROPERTY_LARGEST_AREA_TIE')
        ->and($second['reason'])->toBe($first['reason'])->and($first['locality'])->toBeNull()
        ->and($second['locality'])->toBeNull();
});

it('canonicalizes independent source-row order without changing source records or the resulting allocation', function (): void {
    $input = locality150Fixture('road');
    $first = locality149Qualifier()->qualify($input);
    $input['locations'] = array_reverse($input['locations']);
    $second = locality149Qualifier()->qualify($input);
    expect($first['report_hash'])->toBe($second['report_hash']);
});

it('preserves textual numeric measurement identities rather than deriving ids from PHP array keys', function (): void {
    $input = locality150Fixture();
    foreach (['0', '01', '1'] as $index => $id) {
        $input['locations'][$index]['id'] = $id;
        $input['locations'][$index]['evidence']['record']['location_id'] = $id;
        $input['locations'][$index]['evidence'] = locality149Envelope($input['locations'][$index]['evidence']['record']);
    }
    $input['territorial_measurements']['evidence']['record']['location_ids'] = ['0', '01', '1'];
    $input['territorial_measurements']['evidence'] = locality149Envelope($input['territorial_measurements']['evidence']['record']);
    $report = locality149Qualifier()->qualify($input);
    expect($report['status'])->toBe('LOCAL_TERRITORIAL_LOCATION_QUALIFIED')
        ->and(array_column($report['material_records']['locations'], 'location_id'))->toBe(['0', '01', '1']);
});

it('combines multiple surveyed road pieces in one municipality without creating extra supplies', function (): void {
    $input = locality150Fixture('road');
    $input['locations'][1]['municipality_ibge'] = '3550308';
    $input['locations'][1]['evidence']['record']['municipality_ibge'] = '3550308';
    $input['locations'][1]['evidence'] = locality149Envelope($input['locations'][1]['evidence']['record']);
    $report = locality149Qualifier()->qualify($input);
    $municipalities = array_column($report['jurisdiction_allocations'], null, 'municipality_ibge');
    expect($report['status'])->toBe('LOCAL_TERRITORIAL_LOCATION_QUALIFIED')
        ->and($municipalities)->toHaveCount(2)
        ->and($municipalities['3550308']['share'])->toBe(['numerator' => '2', 'denominator' => '3'])
        ->and($municipalities['3550308']['location_ids'])->toBe(['segment-1', 'segment-2']);
});

it('rejects rehashed allocation claims that do not reproduce from the measured source records', function (): void {
    $report = locality149Qualifier()->qualify(locality150Fixture('road'));
    $report['jurisdiction_allocations'][0]['share']['numerator'] = '2';
    expect(locality149Qualifier()->replay($report))->toBeFalse();
    unset($report['report_hash']);
    $report['report_hash'] = CanonicalJson::hash($report);
    expect(locality149Qualifier()->replay($report))->toBeFalse();
});

it('rejects incomplete unbound ambiguous or numerically inconsistent territorial measurements', function (string $mutation, string $reason): void {
    $input = locality150Fixture();
    switch ($mutation) {
        case 'incomplete': $input['territorial_measurements']['complete'] = false;
            break;
        case 'unit': $input['territorial_measurements']['unit'] = 'HECTARES';
            break;
        case 'float-total': $input['territorial_measurements']['total'] = 100.0;
            break;
        case 'sum': $input = locality150Measures($input, ['30.25', '30.25', '40.50'], '100.00');
            break;
        case 'duplicate': $input['locations'][1]['id'] = $input['locations'][0]['id'];
            break;
        case 'extra': $input['locations'][] = $input['locations'][0];
            break;
        case 'missing': array_pop($input['locations']);
            break;
        case 'zero': $input = locality150Measures($input, ['0', '60.50', '39.50'], '100.00');
            break;
        case 'exponent': $input['locations'][0]['measure'] = '1e2';
            break;
        case 'different-asset': $input['locations'][0]['evidence']['record']['asset_id'] = 'OTHER';
            break;
        case 'different-amount': $input['locations'][0]['evidence']['record']['measure'] = '31.25';
            break;
        case 'future-proof': $input['locations'][0]['evidence']['record']['observed_at'] = '2026-11-03T12:00:00.000001Z';
            break;
        case 'wrong-party': $input['locations'][0]['evidence']['record']['recipient_id'] = 'OTHER';
            break;
        case 'coverage-duplicate': $input['territorial_measurements']['evidence']['record']['location_ids'][1] = 'part-1';
            break;
        case 'coverage-false': $input['territorial_measurements']['evidence']['record']['complete'] = false;
            break;
        case 'coverage-role': $input['territorial_measurements']['evidence']['record']['role'] = 'PROPERTY_AREA';
            break;
    }
    foreach ($input['locations'] as &$location) {
        $location['evidence'] = locality149Envelope($location['evidence']['record']);
    }
    unset($location);
    $input['territorial_measurements']['evidence'] = locality149Envelope($input['territorial_measurements']['evidence']['record']);
    $report = locality149Qualifier()->qualify($input);
    expect($report['status'])->toBe('ACTION_REQUIRED')->and($report['reason'])->toBe($reason)
        ->and($report['locality'])->toBeNull()->and($report['production_ready'])->toBeFalse();
})->with([
    ['incomplete', 'LOCALITY150_COMPLETE_TERRITORY_SET_REQUIRED'],
    ['unit', 'LOCALITY150_MEASUREMENT_CONTRACT_UNIT_OR_TOTAL_UNPROVEN'],
    ['float-total', 'LOCALITY150_MEASUREMENT_CONTRACT_UNIT_OR_TOTAL_UNPROVEN'],
    ['sum', 'LOCALITY150_TOTAL_MEASURE_DOES_NOT_RECONCILE'],
    ['duplicate', 'LOCALITY150_LOCATION_ID_MEASURE_OR_COVERAGE_INVALID'],
    ['extra', 'LOCALITY150_LOCATION_COVERAGE_REQUIRED'],
    ['missing', 'LOCALITY150_LOCATION_COVERAGE_REQUIRED'],
    ['zero', 'LOCALITY150_LOCATION_ID_MEASURE_OR_COVERAGE_INVALID'],
    ['exponent', 'LOCALITY150_LOCATION_ID_MEASURE_OR_COVERAGE_INVALID'],
    ['different-asset', 'LOCALITY150_MEASURED_LOCATION_BINDING_UNPROVEN'],
    ['different-amount', 'LOCALITY150_MEASURED_LOCATION_BINDING_UNPROVEN'],
    ['future-proof', 'LOCALITY150_MEASURED_LOCATION_BINDING_UNPROVEN'],
    ['wrong-party', 'LOCALITY150_MEASURED_LOCATION_BINDING_UNPROVEN'],
    ['coverage-duplicate', 'LOCALITY150_EXPECTED_LOCATION_IDS_INVALID'],
    ['coverage-false', 'LOCALITY150_COVERAGE_RECORD_UNPROVEN'],
    ['coverage-role', 'LOCALITY150_COVERAGE_RECORD_UNPROVEN'],
]);

it('does not qualify free road access as paid road exploitation merely from a geographic extent', function (): void {
    $input = locality150Fixture('road');
    $input['scope_evidence']['record']['paid_exploitation'] = false;
    $input['scope_evidence'] = locality149Envelope($input['scope_evidence']['record']);
    $report = locality149Qualifier()->qualify($input);
    expect($report['reason'])->toBe('LOCALITY150_PAID_ROAD_EXPLOITATION_UNPROVEN')->and($report['locality'])->toBeNull();
});
