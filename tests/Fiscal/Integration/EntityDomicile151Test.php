<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;

require_once dirname(__DIR__, 3).'/scripts/agent_a/domicile151_fixture.php';

it('uses the supplied branch and selects the legal party for each residual scenario', function (string $variant, string $basis, string $party): void {
    $result = domicile151Qualifier()->qualify(domicile151Fixture($variant));
    expect($result['status'])->toBe('LOCAL_ENTITY_DOMICILE_QUALIFIED')
        ->and($result['destination_basis'])->toBe($basis)->and($result['selected_party_id'])->toBe($party)
        ->and($result['selected_establishment_id'])->toBe('BRANCH151')->and($result['headquarters_selected'])->toBeFalse()
        ->and($result['locality'])->toBe(['uf' => 'RJ', 'municipality_ibge' => '3304557'])
        ->and($result['official_registry_queried'])->toBeFalse()->and($result['source_record_authenticity_proven'])->toBeFalse()
        ->and($result['tax_incidence_determined'])->toBeFalse()->and($result['credit_right_determined'])->toBeFalse()
        ->and($result['classification_catalog_integration'])->toBe('PENDING_AGENT_B')
        ->and(domicile151Qualifier()->replay($result))->toBeTrue();
})->with([
    ['domestic', 'ACQUIRER_DOMICILE', 'ACQUIRER151'], ['foreign-acquirer', 'RECIPIENT_DOMICILE', 'RECIPIENT151'],
    ['nononerous', 'RECIPIENT_DOMICILE', 'RECIPIENT151'], ['unincorporated', 'ACQUIRER_DOMICILE', 'ACQUIRER151'],
    ['remote-personal', 'ACQUIRER_DOMICILE', 'ACQUIRER151'],
]);

it('does not let registry order or a headquarters flag override the supplied establishment', function (): void {
    $input = domicile151Fixture();
    $input['registry_evidence']['record']['establishments'] = array_reverse($input['registry_evidence']['record']['establishments']);
    $result = domicile151Qualifier()->qualify(domicile151Rebind($input));
    expect($result['selected_establishment_id'])->toBe('BRANCH151')->and($result['locality']['uf'])->toBe('RJ');
    $input['supply_establishment_evidence']['record']['supplied_establishment_ids'] = ['HQ151'];
    $headquarters = domicile151Qualifier()->qualify(domicile151Rebind($input));
    expect($headquarters['selected_establishment_id'])->toBe('HQ151')->and($headquarters['headquarters_selected'])->toBeTrue()
        ->and($headquarters['locality']['uf'])->toBe('SP');
});

it('does not silently use an acquirer address for a nononerous or foreign-acquirer supply', function (string $variant): void {
    $input = domicile151Fixture($variant);
    $input['registry_evidence']['record']['entity_id'] = 'ACQUIRER151';
    $result = domicile151Qualifier()->qualify(domicile151Rebind($input));
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['locality'])->toBeNull()
        ->and($result['reason'])->toBe('DOMICILE151_SELECTED_PARTY_REGISTRY_BINDING_UNPROVEN');
})->with(['foreign-acquirer', 'nononerous']);

it('keeps unsupported or contradictory material evidence as explicit questions', function (string $mutation, string $reason): void {
    $input = domicile151Fixture();
    switch ($mutation) {
        case 'centralized': $input['scope_evidence']['record']['centralized_purchase'] = true;
            break;
        case 'unknown-centralization': unset($input['scope_evidence']['record']['centralized_purchase']);
            break;
        case 'special-service': $input['scope_evidence']['record']['outside_inciso_I_to_IX'] = false;
            break;
        case 'unregistered': $input['registry_evidence']['record']['regularly_registered'] = false;
            break;
        case 'nonunique-registry': $input['registry_evidence']['record']['unique_registration'] = false;
            break;
        case 'natural-person': $input['registry_evidence']['record']['party_kind'] = 'NATURAL_PERSON';
            break;
        case 'wrong-delivery-party': $input['supply_establishment_evidence']['record']['entity_id'] = 'OTHER151';
            break;
        case 'billing-address': $input['supply_establishment_evidence']['record']['role'] = 'BILLING_ADDRESS';
            break;
        case 'incomplete-supply': $input['supply_establishment_evidence']['record']['complete'] = false;
            break;
        case 'multiple-supplied': $input['supply_establishment_evidence']['record']['supplied_establishment_ids'][] = 'HQ151';
            break;
        case 'missing-supplied': $input['supply_establishment_evidence']['record']['supplied_establishment_ids'] = [];
            break;
        case 'unknown-branch': $input['supply_establishment_evidence']['record']['supplied_establishment_ids'] = ['UNKNOWN151'];
            break;
        case 'coverage': $input['registry_evidence']['record']['complete_for_supplied_establishments'] = false;
            break;
        case 'duplicate-branch': $input['registry_evidence']['record']['establishments'][] = $input['registry_evidence']['record']['establishments'][1];
            break;
        case 'wrong-owner': $input['registry_evidence']['record']['establishments'][1]['entity_id'] = 'OTHER151';
            break;
        case 'expired': $input['registry_evidence']['record']['establishments'][1]['valid_until'] = '2026-10-19';
            break;
        case 'not-opened': $input['registry_evidence']['record']['establishments'][1]['valid_from'] = '2026-10-21';
            break;
        case 'bad-interval': $input['registry_evidence']['record']['establishments'][1]['valid_until'] = '2025-01-01';
            break;
        case 'bad-date': $input['registry_evidence']['record']['establishments'][1]['valid_from'] = '2026-02-30';
            break;
        case 'geographic-conflict': $input['registry_evidence']['record']['establishments'][1]['uf'] = 'SP';
            break;
        case 'registry-abroad': $input['registry_evidence']['record']['country'] = 'US';
            break;
        case 'blank-document': $input['registry_evidence']['record']['document_id'] = ' ';
            break;
        case 'remote-extent': $input = domicile151Fixture('remote-personal');
            $input['scope_evidence']['record']['remotely_performed_at_least_partly'] = false;
            break;
    }
    $result = domicile151Qualifier()->qualify(domicile151Rebind($input));
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['locality'])->toBeNull()
        ->and($result['reason'])->toBe('DOMICILE151_'.$reason)->and($result['missing_fields'])->not->toBeEmpty();
})->with([
    ['centralized', 'CENTRALIZED_PURCHASE_SEPARATE_QUALIFICATION_REQUIRED'], ['unknown-centralization', 'CENTRALIZED_PURCHASE_SEPARATE_QUALIFICATION_REQUIRED'],
    ['special-service', 'RESIDUAL_SCOPE_EXCEPTION_UNPROVEN'], ['unregistered', 'UNREGISTERED_DOMICILE_SEPARATE_QUALIFICATION_REQUIRED'],
    ['nonunique-registry', 'UNREGISTERED_DOMICILE_SEPARATE_QUALIFICATION_REQUIRED'], ['natural-person', 'NATURAL_PERSON_SEPARATE_QUALIFICATION_REQUIRED'],
    ['wrong-delivery-party', 'ACTUAL_SUPPLY_ESTABLISHMENT_BINDING_UNPROVEN'], ['billing-address', 'ACTUAL_SUPPLY_ESTABLISHMENT_BINDING_UNPROVEN'],
    ['incomplete-supply', 'ACTUAL_SUPPLY_ESTABLISHMENT_BINDING_UNPROVEN'], ['multiple-supplied', 'SINGLE_SUPPLIED_ESTABLISHMENT_REQUIRED'],
    ['missing-supplied', 'SINGLE_SUPPLIED_ESTABLISHMENT_REQUIRED'], ['unknown-branch', 'SUPPLIED_ESTABLISHMENT_ABSENT_FROM_REGISTRY'],
    ['coverage', 'REGISTRY_ESTABLISHMENT_COVERAGE_REQUIRED'], ['duplicate-branch', 'REGISTRY_ESTABLISHMENT_INVALID_OR_DUPLICATE'],
    ['wrong-owner', 'REGISTRY_ESTABLISHMENT_INVALID_OR_DUPLICATE'], ['expired', 'SUPPLIED_ESTABLISHMENT_OUTSIDE_RECORDED_VALIDITY'],
    ['not-opened', 'SUPPLIED_ESTABLISHMENT_OUTSIDE_RECORDED_VALIDITY'], ['bad-interval', 'REGISTRY_ESTABLISHMENT_INVALID_OR_DUPLICATE'],
    ['bad-date', 'REGISTRY_ESTABLISHMENT_INVALID_OR_DUPLICATE'], ['geographic-conflict', 'GEOGRAPHY115_UF_CODE_PREFIX_CONFLICT'],
    ['registry-abroad', 'SELECTED_PARTY_REGISTRY_BINDING_UNPROVEN'], ['blank-document', 'SELECTED_PARTY_REGISTRY_BINDING_UNPROVEN'],
    ['remote-extent', 'RESIDUAL_SCOPE_EXCEPTION_UNPROVEN'],
]);

it('enforces inclusive registry date boundaries without making the headquarters a fallback', function (string $from, ?string $until): void {
    $input = domicile151Fixture();
    $input['registry_evidence']['record']['establishments'][1]['valid_from'] = $from;
    $input['registry_evidence']['record']['establishments'][1]['valid_until'] = $until;
    expect(domicile151Qualifier()->qualify(domicile151Rebind($input))['selected_establishment_id'])->toBe('BRANCH151');
})->with([['2026-10-20', null], ['2026-01-01', '2026-10-20'], ['2026-10-20', '2026-10-20']]);

it('rejects changed unbound or future evidence instead of accepting a new hash alone', function (string $key, string $mutation): void {
    $input = domicile151Fixture();
    if ($mutation === 'hash') {
        $input[$key]['record']['document_id'] = 'CHANGED151';
    } else {
        $input[$key]['record'][$mutation === 'party' ? 'supplier_id' : 'observed_at'] = match ($mutation) {
            'party' => 'OTHER151', 'predates-operation' => '2026-10-20T02:59:59Z',
            default => '2026-11-03T12:30:00.000001Z',
        };
        $input[$key] = domicile151Envelope($input[$key]['record']);
    }
    $result = domicile151Qualifier()->qualify($input);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['locality'])->toBeNull();
})->with(['scope_evidence', 'registry_evidence', 'supply_establishment_evidence'])->with(['hash', 'party', 'future', 'predates-operation']);

it('preserves textual establishment identities that would collide after numeric coercion', function (): void {
    $input = domicile151Fixture();
    $input['registry_evidence']['record']['establishments'][0]['id'] = '01';
    $input['registry_evidence']['record']['establishments'][1]['id'] = '1';
    $input['registry_evidence']['record']['establishments'][] = [...$input['registry_evidence']['record']['establishments'][0], 'id' => '0'];
    $input['supply_establishment_evidence']['record']['supplied_establishment_ids'] = ['0'];
    $result = domicile151Qualifier()->qualify(domicile151Rebind($input));
    expect($result['selected_establishment_id'])->toBe('0')->and($result['locality']['uf'])->toBe('SP');
});

it('refuses ambiguous header facts and source or operation chronology', function (string $key, mixed $value, string $reason): void {
    $input = domicile151Fixture();
    $input[$key] = $value;
    $result = domicile151Qualifier()->qualify($input);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe('DOMICILE151_'.$reason);
})->with([
    ['knowledge_at', '2026-11-03T11:18:56.999999Z', 'SOURCE_KNOWLEDGE_UNPROVEN'],
    ['knowledge_at', '2026-11-03T12:30:00-00:00', 'SOURCE_KNOWLEDGE_UNPROVEN'],
    ['operation_date', '2026-11-04', 'OPERATION_DATE_UNPROVEN'], ['operation_date', '2026-02-30', 'OPERATION_DATE_UNPROVEN'],
    ['onerous', 'true', 'PARTY_RESIDENCE_OR_CONSIDERATION_UNPROVEN'],
    ['acquirer_domiciled_in_brazil', null, 'ACQUIRER_IDENTITY_OR_RESIDENCE_UNPROVEN'],
    ['supplier_id', "\u{00A0}SUPPLIER151", 'OPERATION_IDENTITY_REQUIRED'],
]);

it('does not infer domestic destination or consideration from the existence of a registry', function (): void {
    $input = domicile151Fixture('foreign-acquirer');
    $input['recipient_domiciled_in_brazil'] = false;
    expect(domicile151Qualifier()->qualify(domicile151Rebind($input))['reason'])->toBe('DOMICILE151_NO_DOMESTIC_PARTY_UNDER_ART11_X');
    $input = domicile151Fixture('nononerous');
    $input['acquirer_id'] = 'INVENTED151';
    expect(domicile151Qualifier()->qualify($input)['reason'])->toBe('DOMICILE151_NONONEROUS_ACQUIRER_CONTRADICTION');
});

it('requires one entity to have consistent residence across acquirer and recipient roles', function (): void {
    $input = domicile151Fixture('foreign-acquirer');
    $input['acquirer_id'] = $input['recipient_id'];
    expect(domicile151Qualifier()->qualify(domicile151Rebind($input))['reason'])->toBe('DOMICILE151_SAME_PARTY_RESIDENCE_CONTRADICTION');
});

it('compares evidence instants by time zone rather than their text spelling', function (): void {
    $input = domicile151Fixture();
    foreach (['scope_evidence', 'registry_evidence', 'supply_establishment_evidence'] as $key) {
        $input[$key]['record']['observed_at'] = '2026-11-03T09:30:00-03:00';
    }
    $result = domicile151Qualifier()->qualify(domicile151Rebind($input));
    expect($result['status'])->toBe('LOCAL_ENTITY_DOMICILE_QUALIFIED')->and(domicile151Qualifier()->replay($result))->toBeTrue();
});

it('replays the whole evidence decision and rejects rehashed destination changes', function (): void {
    $report = domicile151Qualifier()->qualify(domicile151Fixture());
    $report['locality'] = ['uf' => 'SP', 'municipality_ibge' => '3550308'];
    $saved = $report;
    unset($saved['report_hash']);
    $report['report_hash'] = CanonicalJson::hash($saved);
    expect(domicile151Qualifier()->replay($report))->toBeFalse();
});

it('qualifies a partially remote personal service through the bound A149 and A151 contracts', function (): void {
    $input = domicile151ServiceFixture();
    $result = locality149Qualifier()->qualify($input);
    expect($result['contract'])->toBe('RTC-SERVICE-ENTITY-DOMICILE-2026.11.03-A151')
        ->and($result['status'])->toBe('LOCAL_SERVICE_ENTITY_DOMICILE_QUALIFIED')
        ->and($result['locality'])->toBe(['uf' => 'RJ', 'municipality_ibge' => '3304557'])
        ->and($result['selected_establishment_id'])->toBe('BRANCH151')
        ->and($result['entity_domicile_report'])->toBe($input['entity_domicile_qualification'])
        ->and(locality149Qualifier()->replay($result))->toBeTrue();
    unset($input['entity_domicile_qualification']);
    expect(locality149Qualifier()->qualify($input)['reason'])->toBe('LOCALITY149_RESIDUAL_DOMICILE_REQUIRED_ART11_PAR5');
});

it('rejects a tampered or unrelated domicile report in the optional service linkage', function (string $mutation, string $reason): void {
    $input = domicile151ServiceFixture();
    $entity = $input['entity_domicile_qualification']['operation_input'];
    switch ($mutation) {
        case 'operation': $entity['operation_id'] = 'OTHER151';
            break;
        case 'knowledge': $entity['knowledge_at'] = '2026-11-03T12:31:00Z';
            break;
        case 'category': $entity = domicile151Fixture();
            break;
    }
    if (in_array($mutation, ['operation', 'knowledge', 'category'], true)) {
        $input['entity_domicile_qualification'] = domicile151Qualifier()->qualify(domicile151Rebind($entity));
    } elseif ($mutation === 'missing') {
        $input['entity_domicile_qualification'] = [];
    } else {
        $input['entity_domicile_qualification']['locality'] = ['uf' => 'SP', 'municipality_ibge' => '3550308'];
        if ($mutation === 'rehashed') {
            $payload = $input['entity_domicile_qualification'];
            unset($payload['report_hash']);
            $input['entity_domicile_qualification']['report_hash'] = CanonicalJson::hash($payload);
        }
    }
    $result = locality149Qualifier()->qualify($input);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['locality'])->toBeNull()
        ->and($result['reason'])->toBe($reason);
})->with([
    ['operation', 'LOCALITY151_ENTITY_DOMICILE_OPERATION_BINDING_CONFLICT'],
    ['knowledge', 'LOCALITY151_ENTITY_DOMICILE_OPERATION_BINDING_CONFLICT'],
    ['category', 'LOCALITY151_ENTITY_DOMICILE_REPORT_UNPROVEN'], ['missing', 'LOCALITY151_ENTITY_DOMICILE_REPORT_UNPROVEN'],
    ['tampered', 'LOCALITY151_ENTITY_DOMICILE_REPORT_UNPROVEN'], ['rehashed', 'LOCALITY151_ENTITY_DOMICILE_REPORT_UNPROVEN'],
]);
