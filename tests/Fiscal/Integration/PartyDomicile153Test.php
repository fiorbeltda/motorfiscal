<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;

require_once dirname(__DIR__, 3).'/scripts/agent_a/party153_fixture.php';

it('qualifies material personal and unregistered domicile without inferring a business establishment', function (string $variant, string $basis, string $uf): void {
    $report = domicile151Qualifier()->qualify(party153Fixture($variant));
    expect($report['status'])->toBe('LOCAL_PARTY_DOMICILE_QUALIFIED')->and($report['domicile_basis'])->toBe($basis)
        ->and($report['locality']['uf'])->toBe($uf)->and($report['selected_establishment_id'])->toBeNull()
        ->and($report['official_registry_queried'])->toBeFalse()->and($report['source_record_authenticity_proven'])->toBeFalse()
        ->and($report['tax_incidence_determined'])->toBeFalse()->and($report['credit_right_determined'])->toBeFalse()
        ->and(domicile151Qualifier()->replay($report))->toBeTrue();
})->with([
    ['home', 'PERMANENT_HOME', 'SP'], ['no-home', 'MAIN_ECONOMIC_RELATIONS', 'RJ'], ['multiple-homes', 'MAIN_ECONOMIC_RELATIONS', 'RJ'],
    ['combined', 'SELECTED_NONCONFLICTING_CRITERIA', 'SP'], ['geo-combined', 'SELECTED_NONCONFLICTING_CRITERIA', 'SP'],
    ['fallback', 'DECLARED_ADDRESS_PROVEN_IMPOSSIBILITY', 'SP'], ['conflicting-fallback', 'DECLARED_ADDRESS_PROVEN_IMPOSSIBILITY', 'SP'],
    ['foreign-recipient', 'PERMANENT_HOME', 'SP'], ['nononerous', 'PERMANENT_HOME', 'SP'],
]);

it('uses the domestic recipient for nononerous or foreign-acquirer operations', function (string $variant): void {
    $report = domicile151Qualifier()->qualify(party153Fixture($variant));
    expect($report['destination_basis'])->toBe('RECIPIENT_DOMICILE')->and($report['selected_party_id'])->toBe('RECIPIENT151');
})->with(['nononerous', 'foreign-recipient']);

it('qualifies unregistered business entities with the same statutory criteria instead of a personal home assumption', function (string $kind): void {
    $input = party153Fixture('unregistered-business');
    $input['party_domicile_qualification']['registration_evidence']['record']['party_kind'] = $kind;
    $report = domicile151Qualifier()->qualify(party153Rebind($input));
    expect($report['status'])->toBe('LOCAL_PARTY_DOMICILE_QUALIFIED')->and($report['domicile_basis'])->toBe('SELECTED_NONCONFLICTING_CRITERIA');
})->with(['LEGAL_ENTITY', 'UNINCORPORATED_ENTITY']);

it('requires registered personal facts and keeps the business natural-person context explicit', function (string $mutation, string $reason): void {
    $input = party153Fixture();
    $q = &$input['party_domicile_qualification'];
    switch ($mutation) {
        case 'business-person': $q['registration_evidence']['record']['natural_person_subject_to_registration105'] = true;
            break;
        case 'unknown-person-context': unset($q['registration_evidence']['record']['natural_person_subject_to_registration105']);
            break;
        case 'not-registered': $q['registration_evidence']['record']['regularly_registered'] = false;
            break;
        case 'wrong-kind': $q['registration_evidence']['record']['party_kind'] = 'LEGAL_ENTITY';
            break;
        case 'expired': $q['registration_evidence']['record']['valid_until'] = '2026-10-19';
            break;
        case 'missing-homes': unset($q['material_evidence']['record']['permanent_homes']);
            break;
        case 'incomplete': $q['material_evidence']['record']['complete'] = false;
            break;
        case 'false-absence': $q['material_evidence']['record']['no_permanent_home'] = true;
            break;
        case 'registry-conflict': $q['registration_evidence']['record']['domicile_address'] = party153Address('RJ');
            break;
        case 'invalid-home-date': $q['material_evidence']['record']['permanent_homes'][0]['valid_from'] = '2026-02-30';
            break;
        case 'duplicate-home': $q['material_evidence']['record']['permanent_homes'][] = $q['material_evidence']['record']['permanent_homes'][0];
            break;
        case 'economic-unknown': $input = party153Fixture('multiple-homes');
            $input['party_domicile_qualification']['material_evidence']['record']['economic_relevance_qualified'] = false;
            break;
    }
    unset($q);
    $result = domicile151Qualifier()->qualify(party153Rebind($input));
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['locality'])->toBeNull()->and($result['reason'])->toBe('DOMICILE153_'.$reason);
})->with([
    ['business-person', 'NATURAL_PERSON_ART105_ESTABLISHMENT_QUALIFICATION_REQUIRED'], ['unknown-person-context', 'NATURAL_PERSON_ART105_ESTABLISHMENT_QUALIFICATION_REQUIRED'],
    ['not-registered', 'REGULAR_NATURAL_PERSON_REGISTRATION_REQUIRED'], ['wrong-kind', 'REGULAR_NATURAL_PERSON_REGISTRATION_REQUIRED'],
    ['expired', 'REGISTRATION_STATE_BINDING_REQUIRED'], ['missing-homes', 'COMPLETE_PERMANENT_HOME_SET_REQUIRED'],
    ['incomplete', 'PERSONAL_DOMICILE_MATERIAL_BINDING_REQUIRED'], ['false-absence', 'PERMANENT_HOME_ABSENCE_CONTRADICTION'],
    ['registry-conflict', 'REGISTERED_AND_MATERIAL_DOMICILE_CONFLICT'], ['invalid-home-date', 'PERMANENT_HOME_ID_ADDRESS_OR_INTERVAL_INVALID'],
    ['duplicate-home', 'PERMANENT_HOME_ID_ADDRESS_OR_INTERVAL_INVALID'], ['economic-unknown', 'MAIN_ECONOMIC_RELATIONS_QUALIFICATION_REQUIRED'],
]);

it('does not replace one permanent home with an economic preference or count a closed home', function (): void {
    $input = party153Fixture();
    $q = &$input['party_domicile_qualification'];
    $q['material_evidence']['record']['permanent_homes'][] = [...$q['material_evidence']['record']['permanent_homes'][0],
        'id' => 'CLOSED153', 'address' => party153Address('RJ'), 'valid_until' => '2026-10-19'];
    unset($q);
    $result = domicile151Qualifier()->qualify(party153Rebind($input));
    expect($result['domicile_basis'])->toBe('PERMANENT_HOME')->and($result['active_permanent_home_count'])->toBe(1)
        ->and($result['locality']['uf'])->toBe('SP');
});

it('counts a foreign permanent home when deciding whether economic relevance is required', function (): void {
    $input = party153Fixture('multiple-homes');
    $input['party_domicile_qualification']['material_evidence']['record']['permanent_homes'][0]['address'] = [
        'in_brazil' => false, 'granularity' => 'FOREIGN_ADDRESS', 'foreign_address_id' => 'SYNTHETIC.FOREIGN.HOME153'];
    $result = domicile151Qualifier()->qualify(party153Rebind($input));
    expect($result['active_permanent_home_count'])->toBe(2)->and($result['domicile_basis'])->toBe('MAIN_ECONOMIC_RELATIONS')
        ->and($result['locality']['uf'])->toBe('RJ');
});

it('respects supplier choice and preserves a conflicting unselected criterion', function (): void {
    $report = domicile151Qualifier()->qualify(party153Fixture('combined'));
    expect($report['selected_criterion_kinds'])->toBe(['COMMERCIAL_INFO', 'DECLARED_ADDRESS'])
        ->and($report['nonselected_criteria'])->toBe([['criterion_id' => 'geo153', 'status' => 'NOT_SELECTED_CONFLICTING']]);
    $input = party153Fixture('combined');
    $input['party_domicile_qualification']['criteria'][2]['record_hash'] = str_repeat('0', 64);
    $unqualified = domicile151Qualifier()->qualify($input);
    expect($unqualified['status'])->toBe('LOCAL_PARTY_DOMICILE_QUALIFIED')
        ->and($unqualified['nonselected_criteria'][0]['status'])->toBe('NOT_SELECTED_UNQUALIFIED');
});

it('keeps a municipal geolocation criterion coarse and obtains street detail only from the declaration', function (): void {
    $report = domicile151Qualifier()->qualify(party153Fixture('geo-combined'));
    $geolocation = array_values(array_filter($report['material_records']['selected_criteria'], static fn (array $row): bool => $row['kind'] === 'IP_GEOLOCATION'))[0];
    expect($geolocation['resolved_address']['components'])->toBe([])->and($geolocation['resolved_address']['granularity'])->toBe('MUNICIPALITY')
        ->and($report['domicile_address']['components']['street'])->toBe('SYNTHETIC.STREET.SP');
});

it('does not equate distinct street addresses inside the same municipality', function (string $component, string $other): void {
    $input = party153Fixture('combined');
    $input['party_domicile_qualification']['criteria'][1]['record']['address']['components'][$component] = $other;
    $result = domicile151Qualifier()->qualify(party153Rebind($input));
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe('DOMICILE153_SELECTED_ADDRESSES_CONFLICT');
})->with([['street', 'OTHER.STREET'], ['number', '20'], ['postal_code', '01000001']]);

it('requires a valid explicit choice of distinct criteria with source-specific material bindings', function (string $mutation, string $reason): void {
    $input = party153Fixture('combined');
    $q = &$input['party_domicile_qualification'];
    switch ($mutation) {
        case 'one': $q['selection_evidence']['record']['selected_criterion_ids'] = ['declared153'];
            break;
        case 'duplicate': $q['selection_evidence']['record']['selected_criterion_ids'] = ['declared153', 'declared153'];
            break;
        case 'unknown': $q['selection_evidence']['record']['selected_criterion_ids'] = ['declared153', 'UNKNOWN153'];
            break;
        case 'same-kind': $q['criteria'][1]['record']['kind'] = 'DECLARED_ADDRESS';
            break;
        case 'not-supplier-choice': $q['selection_evidence']['record']['supplier_selection_confirmed'] = false;
            break;
        case 'other-operation-info': $q['criteria'][1]['record']['collected_in_operation'] = false;
            break;
        case 'not-declared': $q['criteria'][0]['record']['declared_to_supplier'] = false;
            break;
        case 'registered': $q['registration_evidence']['record']['regularly_registered'] = true;
            break;
        case 'raw-id-duplicate': $q['criteria'][1]['record']['criterion_id'] = 'declared153';
            break;
        case 'geography': $q['criteria'][0]['record']['address']['uf'] = 'RJ';
            $q['criteria'][1]['record']['address']['uf'] = 'RJ';
            break;
    }
    unset($q);
    $result = domicile151Qualifier()->qualify(party153Rebind($input));
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe('DOMICILE153_'.$reason);
})->with([
    ['one', 'SUPPLIER_CRITERIA_SELECTION_REQUIRED'], ['duplicate', 'SELECTED_CRITERION_UNPROVEN_OR_DUPLICATE'],
    ['unknown', 'SELECTED_CRITERION_UNPROVEN_OR_DUPLICATE'], ['same-kind', 'TWO_DISTINCT_CRITERIA_REQUIRED'],
    ['not-supplier-choice', 'SUPPLIER_CRITERIA_SELECTION_REQUIRED'], ['other-operation-info', 'SELECTED_CRITERION_UNPROVEN_OR_DUPLICATE'],
    ['not-declared', 'SELECTED_CRITERION_UNPROVEN_OR_DUPLICATE'], ['registered', 'REGISTERED_PARTY_CANNOT_USE_UNREGISTERED_CRITERIA'],
    ['raw-id-duplicate', 'CRITERION_ID_AMBIGUOUS'], ['geography', 'GEOGRAPHY115_UF_CODE_PREFIX_CONFLICT'],
]);

it('does not use the acquirer payment arrangement address as proof of a distinct recipient domicile', function (): void {
    $input = party153Fixture('combined');
    $input['acquirer_domiciled_in_brazil'] = false;
    $q = &$input['party_domicile_qualification'];
    $q['registration_evidence']['record']['party_id'] = 'RECIPIENT151';
    $q['selection_evidence']['record']['party_id'] = 'RECIPIENT151';
    foreach ($q['criteria'] as &$envelope) {
        $envelope['record']['party_id'] = 'RECIPIENT151';
    }
    unset($envelope);
    $q['criteria'][1]['record']['kind'] = 'PAYMENT_ARRANGEMENT';
    unset($q);
    $result = domicile151Qualifier()->qualify(party153Rebind($input));
    expect($result['reason'])->toBe('DOMICILE153_SELECTED_CRITERION_UNPROVEN_OR_DUPLICATE');
});

it('requires full impossibility evidence and forbids declared fallback when a valid pair is available', function (string $mutation, string $reason): void {
    $input = party153Fixture('fallback');
    $q = &$input['party_domicile_qualification'];
    switch ($mutation) {
        case 'missing': unset($q['impossibility_evidence']);
            break;
        case 'coverage': $q['impossibility_evidence']['record']['criteria_coverage_complete'] = false;
            break;
        case 'three-types': array_pop($q['impossibility_evidence']['record']['availability']);
            break;
        case 'unknown-availability': $q['impossibility_evidence']['record']['availability'][1]['available'] = 'unknown';
            break;
        case 'no-unavailability-proof': unset($q['impossibility_evidence']['record']['availability'][1]['unavailability_evidence_id']);
            break;
        case 'omitted-available': $q['criteria'][] = party153Criterion('commercial153', 'COMMERCIAL_INFO', 'ACQUIRER151');
            break;
        case 'pair-exists':
            $q['criteria'][] = party153Criterion('commercial153', 'COMMERCIAL_INFO', 'ACQUIRER151');
            $q['impossibility_evidence'] = party153Impossibility($q['criteria'], 'ACQUIRER151');
            break;
        case 'two-declarations':
            $q['criteria'][] = party153Criterion('declared-other153', 'DECLARED_ADDRESS', 'ACQUIRER151', 'RJ');
            $q['impossibility_evidence'] = party153Impossibility($q['criteria'], 'ACQUIRER151');
            break;
        case 'ip-only':
            $q['criteria'] = [party153Criterion('geo153', 'IP_GEOLOCATION', 'ACQUIRER151')];
            $q['impossibility_evidence'] = party153Impossibility($q['criteria'], 'ACQUIRER151');
            break;
    }
    unset($q);
    $result = domicile151Qualifier()->qualify(party153Rebind($input));
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['locality'])->toBeNull()->and($result['reason'])->toBe('DOMICILE153_'.$reason);
})->with([
    ['missing', 'COMPLETE_IMPOSSIBILITY_PROOF_REQUIRED'], ['coverage', 'COMPLETE_IMPOSSIBILITY_PROOF_REQUIRED'],
    ['three-types', 'COMPLETE_IMPOSSIBILITY_PROOF_REQUIRED'], ['unknown-availability', 'FOUR_CRITERIA_AVAILABILITY_REQUIRED'],
    ['no-unavailability-proof', 'AVAILABILITY_RECORD_COVERAGE_CONFLICT'], ['omitted-available', 'AVAILABILITY_RECORD_COVERAGE_CONFLICT'],
    ['pair-exists', 'NONCONFLICTING_PAIR_EXISTS_FALLBACK_FORBIDDEN'], ['two-declarations', 'UNIQUE_DECLARED_ADDRESS_REQUIRED'], ['ip-only', 'UNIQUE_DECLARED_ADDRESS_REQUIRED'],
]);

it('rejects selected future changed or unbound records in each material profile', function (string $variant, string $field, string $mutation): void {
    $input = party153Fixture($variant);
    $q = &$input['party_domicile_qualification'];
    $envelope = &$q[$field];
    if ($mutation === 'hash') {
        $envelope['record']['document_id'] = 'CHANGED153';
    } else {
        $envelope['record'][$mutation === 'party' ? 'supplier_id' : 'observed_at'] = $mutation === 'party' ? 'OTHER153' : '2026-11-03T17:05:00.000001Z';
        $envelope['record_hash'] = CanonicalJson::hash($envelope['record']);
    }
    unset($q, $envelope);
    $result = domicile151Qualifier()->qualify($input);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['locality'])->toBeNull();
})->with([
    ['home', 'registration_evidence'], ['home', 'material_evidence'], ['combined', 'selection_evidence'], ['fallback', 'impossibility_evidence'],
])->with(['hash', 'party', 'future']);

it('preserves textual criterion identities and requires selected record integrity', function (): void {
    $input = party153Fixture('combined');
    $input['party_domicile_qualification']['criteria'][0]['record']['criterion_id'] = '0';
    $input['party_domicile_qualification']['criteria'][1]['record']['criterion_id'] = '01';
    $input['party_domicile_qualification']['selection_evidence']['record']['selected_criterion_ids'] = ['0', '01'];
    $input = party153Rebind($input);
    expect(domicile151Qualifier()->qualify($input)['selected_criterion_ids'])->toBe(['0', '01']);
    $input['party_domicile_qualification']['criteria'][0]['record_hash'] = str_repeat('0', 64);
    expect(domicile151Qualifier()->qualify($input)['reason'])->toBe('DOMICILE153_SELECTED_CRITERION_UNPROVEN_OR_DUPLICATE');
});

it('rejects mixed domicile profiles rather than ignoring contrary registry or centralized records', function (string $other): void {
    $input = party153Fixture();
    $input[$other] = ['record' => ['regularly_registered' => false]];
    expect(domicile151Qualifier()->qualify($input)['reason'])->toBe('DOMICILE153_INCOMPATIBLE_DOMICILE_PROFILES');
})->with(['registry_evidence', 'supply_establishment_evidence', 'centralized_purchase_evidence']);

it('links the party report to a remote personal service and rejects a rehashed address change', function (): void {
    $input = party153ServiceFixture();
    $report = locality149Qualifier()->qualify($input);
    expect($report['contract'])->toBe('RTC-SERVICE-PARTY-DOMICILE-2026.11.03-A153')
        ->and($report['status'])->toBe('LOCAL_SERVICE_PARTY_DOMICILE_QUALIFIED')->and(locality149Qualifier()->replay($report))->toBeTrue();
    $report['locality'] = ['uf' => 'RJ', 'municipality_ibge' => '3304557'];
    $payload = $report;
    unset($payload['report_hash']);
    $report['report_hash'] = CanonicalJson::hash($payload);
    expect(locality149Qualifier()->replay($report))->toBeFalse();
});
