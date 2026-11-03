<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;

require_once dirname(__DIR__, 3).'/scripts/agent_a/centralized152_fixture.php';

it('selects the matrix for the four qualified centralized acquisition families', function (string $category): void {
    $report = domicile151Qualifier()->qualify(centralized152Fixture($category));
    expect($report['contract'])->toBe('RTC-CENTRALIZED-ENTITY-DOMICILE-2026.11.03-A152')
        ->and($report['status'])->toBe('LOCAL_CENTRALIZED_ENTITY_DOMICILE_QUALIFIED')
        ->and($report['selected_party_id'])->toBe('ACQUIRER151')->and($report['selected_establishment_id'])->toBe('HQ151')
        ->and($report['locality'])->toBe(['uf' => 'SP', 'municipality_ibge' => '3550308'])
        ->and($report['covered_establishment_ids'])->toBe(['BRANCH151'])
        ->and($report['external_credit_restriction_qualification_consumed'])->toBeTrue()
        ->and($report['credit_right_determined'])->toBeFalse()->and($report['credit_restriction_assessment_authenticated'])->toBeFalse()
        ->and($report['official_registry_queried'])->toBeFalse()->and($report['tax_incidence_determined'])->toBeFalse()
        ->and(domicile151Qualifier()->replay($report))->toBeTrue();
})->with(['FIXED_COMMUNICATION', 'MOVABLE_RENTAL', 'RESIDUAL_SERVICE', 'PERSONAL_REMOTE']);

it('selects the recorded matrix instead of a hardcoded municipality or the terminal', function (): void {
    $input = centralized152Fixture();
    $input['registry_evidence']['record']['establishments'][0]['headquarters'] = false;
    $input['registry_evidence']['record']['establishments'][1]['headquarters'] = true;
    $input['centralized_purchase_evidence']['record']['headquarters_establishment_id'] = 'BRANCH151';
    $result = domicile151Qualifier()->qualify(centralized152Rebind($input));
    expect($result['selected_establishment_id'])->toBe('BRANCH151')->and($result['locality']['uf'])->toBe('RJ');
});

it('requires material conditions instead of inferring the centralized exception', function (string $mutation, string $reason): void {
    $input = centralized152Fixture();
    switch ($mutation) {
        case 'foreign-acquirer': $input['acquirer_domiciled_in_brazil'] = false;
            break;
        case 'nononerous': $input['onerous'] = false;
            $input['acquirer_id'] = null;
            $input['acquirer_domiciled_in_brazil'] = null;
            break;
        case 'not-centralized': $input['scope_evidence']['record']['centralized_purchase'] = false;
            break;
        case 'special-service': $input['supply_category'] = 'IMMOVABLE_RELATED';
            break;
        case 'not-physical-rental': $input = centralized152Fixture('MOVABLE_RENTAL');
            $input['scope_evidence']['record']['physical_movable_rental'] = false;
            break;
        case 'not-residual': $input = centralized152Fixture('RESIDUAL_SERVICE');
            $input['scope_evidence']['record']['outside_inciso_I_to_IX'] = false;
            break;
        case 'not-remote': $input = centralized152Fixture('PERSONAL_REMOTE');
            $input['scope_evidence']['record']['remotely_performed_at_least_partly'] = false;
            break;
        case 'purchase-other-party': $input['centralized_purchase_evidence']['record']['entity_id'] = 'OTHER152';
            break;
        case 'incomplete-acquisition': $input['centralized_purchase_evidence']['record']['complete'] = false;
            break;
        case 'ibs-not-regular': $input['regular_regime_evidence']['record']['ibs_regime'] = 'SIMPLES_NACIONAL';
            break;
        case 'cbs-not-regular': $input['regular_regime_evidence']['record']['cbs_regime'] = 'UNKNOWN';
            break;
        case 'regime-expired': $input['regular_regime_evidence']['record']['valid_until'] = '2026-10-19';
            break;
        case 'no-credit-qualification': unset($input['credit_restriction_evidence']);
            break;
        case 'ibs-prohibited': $input['credit_restriction_evidence']['record']['ibs_credit_prohibited'] = true;
            break;
        case 'cbs-prohibited': $input['credit_restriction_evidence']['record']['cbs_credit_prohibited'] = true;
            break;
        case 'cbs-unknown': unset($input['credit_restriction_evidence']['record']['cbs_credit_prohibited']);
            break;
        case 'untyped-predicate': $input['credit_restriction_evidence']['record']['ibs_credit_prohibited'] = 'false';
            break;
        case 'partial-qualification': $input['credit_restriction_evidence']['record']['complete'] = false;
            break;
        case 'unreferenced-qualification': $input['credit_restriction_evidence']['record']['qualification_reference'] = ' ';
            break;
        case 'credit-other-party': $input['credit_restriction_evidence']['record']['entity_id'] = 'OTHER152';
            break;
        case 'registry-incomplete': $input['registry_evidence']['record']['all_establishments_included'] = false;
            break;
        case 'not-registered': $input['registry_evidence']['record']['regularly_registered'] = false;
            break;
        case 'one-active': $input['registry_evidence']['record']['establishments'][1]['valid_until'] = '2026-10-19';
            break;
        case 'no-matrix': $input['registry_evidence']['record']['establishments'][0]['headquarters'] = false;
            break;
        case 'two-matrices': $input['registry_evidence']['record']['establishments'][1]['headquarters'] = true;
            break;
        case 'wrong-matrix-reference': $input['centralized_purchase_evidence']['record']['headquarters_establishment_id'] = 'BRANCH151';
            break;
        case 'duplicate-id': $input['registry_evidence']['record']['establishments'][1]['id'] = 'HQ151';
            break;
        case 'wrong-owner': $input['registry_evidence']['record']['establishments'][1]['entity_id'] = 'OTHER152';
            break;
        case 'impossible-date': $input['registry_evidence']['record']['establishments'][1]['valid_from'] = '2026-02-30';
            break;
        case 'coverage-empty': $input['centralized_purchase_evidence']['record']['covered_establishment_ids'] = [];
            break;
        case 'coverage-other': $input['centralized_purchase_evidence']['record']['covered_establishment_ids'] = ['OTHER152'];
            break;
        case 'coverage-duplicate': $input['centralized_purchase_evidence']['record']['covered_establishment_ids'] = ['BRANCH151', 'BRANCH151'];
            break;
        case 'wrong-geography': $input['registry_evidence']['record']['establishments'][1]['uf'] = 'SP';
            break;
    }
    $result = domicile151Qualifier()->qualify(centralized152Rebind($input));
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['locality'])->toBeNull()
        ->and($result['reason'])->toBe('DOMICILE152_'.$reason)->and($result['missing_fields'])->not->toBeEmpty();
})->with([
    ['foreign-acquirer', 'DOMESTIC_ACQUIRER_REQUIRED'], ['nononerous', 'DOMESTIC_ACQUIRER_REQUIRED'],
    ['not-centralized', 'SCOPE_RECORD_UNPROVEN'], ['special-service', 'SCOPE_RECORD_UNPROVEN'],
    ['not-physical-rental', 'SCOPE_RECORD_UNPROVEN'], ['not-residual', 'SCOPE_RECORD_UNPROVEN'], ['not-remote', 'SCOPE_RECORD_UNPROVEN'],
    ['purchase-other-party', 'ACQUISITION_BINDING_UNPROVEN'], ['incomplete-acquisition', 'ACQUISITION_BINDING_UNPROVEN'],
    ['ibs-not-regular', 'BOTH_REGULAR_REGIMES_UNPROVEN'], ['cbs-not-regular', 'BOTH_REGULAR_REGIMES_UNPROVEN'], ['regime-expired', 'BOTH_REGULAR_REGIMES_UNPROVEN'],
    ['no-credit-qualification', 'EXTERNAL_CREDIT_PROHIBITION_QUALIFICATION_REQUIRED'], ['ibs-prohibited', 'EXTERNAL_CREDIT_PROHIBITION_QUALIFICATION_REQUIRED'],
    ['cbs-prohibited', 'EXTERNAL_CREDIT_PROHIBITION_QUALIFICATION_REQUIRED'], ['cbs-unknown', 'EXTERNAL_CREDIT_PROHIBITION_QUALIFICATION_REQUIRED'],
    ['untyped-predicate', 'EXTERNAL_CREDIT_PROHIBITION_QUALIFICATION_REQUIRED'], ['partial-qualification', 'EXTERNAL_CREDIT_PROHIBITION_QUALIFICATION_REQUIRED'],
    ['unreferenced-qualification', 'EXTERNAL_CREDIT_PROHIBITION_QUALIFICATION_REQUIRED'], ['credit-other-party', 'EXTERNAL_CREDIT_PROHIBITION_QUALIFICATION_REQUIRED'],
    ['registry-incomplete', 'COMPLETE_ACQUIRER_REGISTRY_REQUIRED'], ['not-registered', 'COMPLETE_ACQUIRER_REGISTRY_REQUIRED'],
    ['one-active', 'MORE_THAN_ONE_ACTIVE_ESTABLISHMENT_REQUIRED'], ['no-matrix', 'UNIQUE_ACTIVE_HEADQUARTERS_BINDING_REQUIRED'],
    ['two-matrices', 'UNIQUE_ACTIVE_HEADQUARTERS_BINDING_REQUIRED'], ['wrong-matrix-reference', 'UNIQUE_ACTIVE_HEADQUARTERS_BINDING_REQUIRED'],
    ['duplicate-id', 'ESTABLISHMENT_IDENTITY_OR_INTERVAL_INVALID'], ['wrong-owner', 'ESTABLISHMENT_IDENTITY_OR_INTERVAL_INVALID'],
    ['impossible-date', 'ESTABLISHMENT_IDENTITY_OR_INTERVAL_INVALID'], ['coverage-empty', 'ACQUISITION_ESTABLISHMENT_COVERAGE_REQUIRED'],
    ['coverage-other', 'ACQUISITION_ESTABLISHMENT_COVERAGE_CONFLICT'], ['coverage-duplicate', 'ACQUISITION_ESTABLISHMENT_COVERAGE_CONFLICT'],
    ['wrong-geography', 'GEOGRAPHY115_UF_CODE_PREFIX_CONFLICT'],
]);

it('does not substitute payment or credit balance for the separately qualified prohibition predicate', function (): void {
    $input = centralized152Fixture();
    $input['credit_restriction_evidence']['record']['current_credit_balance'] = '0.00';
    $input['credit_restriction_evidence']['record']['appropriation_status'] = 'CONDITIONAL';
    $input['credit_restriction_evidence']['record']['tax_extinguished'] = false;
    $result = domicile151Qualifier()->qualify(centralized152Rebind($input));
    expect($result['status'])->toBe('LOCAL_CENTRALIZED_ENTITY_DOMICILE_QUALIFIED')->and($result['credit_right_determined'])->toBeFalse();
    unset($input['credit_restriction_evidence']['record']['cbs_credit_prohibited']);
    expect(domicile151Qualifier()->qualify(centralized152Rebind($input))['status'])->toBe('ACTION_REQUIRED');
});

it('keeps integrity party binding and future observation guards for each supplied qualification', function (string $key, string $mutation): void {
    $input = centralized152Fixture();
    if ($mutation === 'hash') {
        $input[$key]['record']['document_id'] = 'CHANGED152';
    } else {
        $input[$key]['record'][$mutation === 'party' ? 'supplier_id' : 'observed_at'] = $mutation === 'party'
            ? 'OTHER152' : '2026-11-03T12:50:00.000001Z';
        $input[$key]['record_hash'] = CanonicalJson::hash($input[$key]['record']);
    }
    $result = domicile151Qualifier()->qualify($input);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['locality'])->toBeNull();
})->with(['scope_evidence', 'registry_evidence', 'centralized_purchase_evidence', 'regular_regime_evidence', 'credit_restriction_evidence'])
    ->with(['hash', 'party', 'future']);

it('uses active registry intervals and exact boundary instants without counting a closed matrix', function (): void {
    $input = centralized152Fixture();
    foreach (['regular_regime_evidence', 'registry_evidence'] as $key) {
        $input[$key]['record']['observed_at'] = '2026-11-03T09:50:00-03:00';
    }
    $input['registry_evidence']['record']['establishments'][0]['valid_until'] = '2026-10-19';
    $input['registry_evidence']['record']['establishments'][1]['headquarters'] = true;
    $input['registry_evidence']['record']['establishments'][1]['valid_from'] = '2026-10-20';
    $input['registry_evidence']['record']['establishments'][1]['valid_until'] = '2026-10-20';
    $input['registry_evidence']['record']['establishments'][] = [...$input['registry_evidence']['record']['establishments'][0],
        'id' => 'THIRD152', 'headquarters' => false, 'valid_until' => null];
    $input['centralized_purchase_evidence']['record']['headquarters_establishment_id'] = 'BRANCH151';
    $result = domicile151Qualifier()->qualify(centralized152Rebind($input));
    expect($result['selected_establishment_id'])->toBe('BRANCH151')->and($result['active_establishment_ids'])->toBe(['BRANCH151', 'THIRD152']);
});

it('preserves textual matrix and branch identifiers without numeric coercion', function (): void {
    $input = centralized152Fixture();
    $input['registry_evidence']['record']['establishments'][0]['id'] = '0';
    $input['registry_evidence']['record']['establishments'][1]['id'] = '01';
    $input['centralized_purchase_evidence']['record']['headquarters_establishment_id'] = '0';
    $input['centralized_purchase_evidence']['record']['covered_establishment_ids'] = ['01'];
    $result = domicile151Qualifier()->qualify(centralized152Rebind($input));
    expect($result['selected_establishment_id'])->toBe('0')->and($result['covered_establishment_ids'])->toBe(['01']);
});

it('links the centralized matrix to fixed communications and partially remote personal services', function (string $category): void {
    $input = centralized152ServiceFixture($category);
    $report = locality149Qualifier()->qualify($input);
    expect($report['contract'])->toBe('RTC-SERVICE-CENTRALIZED-DOMICILE-2026.11.03-A152')
        ->and($report['status'])->toBe('LOCAL_SERVICE_CENTRALIZED_DOMICILE_QUALIFIED')
        ->and($report['locality'])->toBe(['uf' => 'SP', 'municipality_ibge' => '3550308'])
        ->and(locality149Qualifier()->replay($report))->toBeTrue();
})->with(['FIXED_COMMUNICATION', 'PERSONAL_REMOTE']);

it('does not accept an unrelated rehashed or contradictory centralized qualification in a service', function (string $mutation, string $reason): void {
    $input = centralized152ServiceFixture();
    if ($mutation === 'scope') {
        $input['scope_evidence']['record']['centralized_purchase'] = false;
        $input['scope_evidence'] = locality149Envelope($input['scope_evidence']['record']);
    } elseif ($mutation === 'operation') {
        $entity = $input['centralized_purchase_qualification']['operation_input'];
        $entity['operation_id'] = 'OTHER152';
        $input['centralized_purchase_qualification'] = domicile151Qualifier()->qualify(centralized152Rebind($entity));
    } else {
        $input['centralized_purchase_qualification']['selected_establishment_id'] = 'BRANCH151';
        $saved = $input['centralized_purchase_qualification'];
        unset($saved['report_hash']);
        $input['centralized_purchase_qualification']['report_hash'] = CanonicalJson::hash($saved);
    }
    $report = locality149Qualifier()->qualify($input);
    expect($report['status'])->toBe('ACTION_REQUIRED')->and($report['reason'])->toBe($reason);
})->with([
    ['scope', 'LOCALITY152_CENTRALIZATION_SCOPE_BINDING_CONFLICT'],
    ['operation', 'LOCALITY152_ENTITY_DOMICILE_OPERATION_BINDING_CONFLICT'],
    ['rehashed', 'LOCALITY152_ENTITY_DOMICILE_REPORT_UNPROVEN'],
]);

it('keeps the original question when the fixed-service centralized opt-in is absent', function (): void {
    $input = centralized152ServiceFixture();
    unset($input['centralized_purchase_qualification']);
    expect(locality149Qualifier()->qualify($input)['reason'])->toBe('LOCALITY149_CENTRALIZED_PURCHASE_QUALIFICATION_REQUIRED_ART11_PAR4');
});

it('requires personal-service and entity scopes to agree about centralization in both directions', function (bool $centralized): void {
    $input = $centralized ? centralized152ServiceFixture('PERSONAL_REMOTE') : domicile151ServiceFixture();
    $input['scope_evidence']['record']['centralized_purchase'] = ! $centralized;
    $input['scope_evidence'] = locality149Envelope($input['scope_evidence']['record']);
    $result = locality149Qualifier()->qualify($input);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe('LOCALITY152_CENTRALIZATION_SCOPE_BINDING_CONFLICT');
})->with([true, false]);

it('rejects a rehashed matrix replacement in the whole centralized report replay', function (): void {
    $report = domicile151Qualifier()->qualify(centralized152Fixture());
    $report['selected_establishment_id'] = 'BRANCH151';
    $payload = $report;
    unset($payload['report_hash']);
    $report['report_hash'] = CanonicalJson::hash($payload);
    expect(domicile151Qualifier()->replay($report))->toBeFalse();
});
