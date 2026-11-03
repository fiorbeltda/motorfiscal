<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;

require_once dirname(__DIR__, 3).'/scripts/agent_a/locality149_fixture.php';

it('qualifies the material locality prescribed for each special service family without selecting tax codes', function (string $category, string $inciso, string $role): void {
    $report = locality149Qualifier()->qualify(locality149Fixture($category));
    expect($report['status'])->toBe('LOCAL_SERVICE_LOCATION_QUALIFIED')
        ->and($report['source_article'])->toBe('LC214 art.11 '.$inciso)
        ->and($report['location_role'])->toBe($role)
        ->and($report['locality'])->toBe(['uf' => 'SP', 'municipality_ibge' => '3550308'])
        ->and($report['cIndOp_selected'])->toBeFalse()->and($report['tax_rate_selected'])->toBeFalse()
        ->and($report['source_record_authenticity_proven'])->toBeFalse()
        ->and($report['historical_juridical_validity_proven'])->toBeFalse()
        ->and(locality149Qualifier()->replay($report))->toBeTrue();
})->with([
    ['IMMOVABLE_RELATED', 'II', 'PROPERTY_LOCATION'], ['PERSONAL_PRESENTIAL', 'III', 'PERSONAL_PERFORMANCE'],
    ['EVENT_ORGANIZATION', 'IV', 'EVENT_LOCATION'], ['MOVABLE_PHYSICAL_SERVICE', 'V', 'PHYSICAL_PERFORMANCE'],
    ['PORT_SERVICE', 'V', 'PORT_LOCATION'], ['PASSENGER_TRANSPORT', 'VI', 'TRANSPORT_START'],
    ['CARGO_TRANSPORT', 'VII', 'DOCUMENTED_DELIVERY'], ['FIXED_COMMUNICATION', 'IX', 'TERMINAL_INSTALLATION'],
]);

it('keeps exceptions as explicit dependencies rather than choosing a generic or first-listed locality', function (string $mutation, string $reason): void {
    $category = match ($mutation) {
        'remote', 'unknown-mode', 'unknown-remote' => 'PERSONAL_PRESENTIAL',
        'centralized', 'unknown-centralization' => 'FIXED_COMMUNICATION',
        'two-properties' => 'IMMOVABLE_RELATED', default => 'CARGO_TRANSPORT',
    };
    $input = locality149Fixture($category);
    $scope = $input['scope_evidence']['record'];
    switch ($mutation) {
        case 'remote': $scope['partially_remote'] = true;
            break;
        case 'unknown-mode': unset($scope['delivery_mode']);
            break;
        case 'unknown-remote': unset($scope['partially_remote']);
            break;
        case 'centralized': $scope['centralized_purchase'] = true;
            break;
        case 'unknown-centralization': unset($scope['centralized_purchase']);
            break;
        case 'two-properties': $input['locations'][] = $input['locations'][0];
            break;
        case 'road': $input['service_category'] = 'ROAD_EXPLOITATION';
            break;
    }
    $input['scope_evidence'] = locality149Envelope($scope);
    $report = locality149Qualifier()->qualify($input);
    expect($report['status'])->toBe('ACTION_REQUIRED')->and($report['reason'])->toBe($reason)
        ->and($report['locality'])->toBeNull()->and($report['tax_rate_selected'])->toBeFalse();
})->with([
    ['remote', 'LOCALITY149_RESIDUAL_DOMICILE_REQUIRED_ART11_PAR5'],
    ['unknown-mode', 'LOCALITY149_DELIVERY_MODE_UNPROVEN'],
    ['unknown-remote', 'LOCALITY149_REMOTE_EXTENT_UNPROVEN'],
    ['centralized', 'LOCALITY149_CENTRALIZED_PURCHASE_QUALIFICATION_REQUIRED_ART11_PAR4'],
    ['unknown-centralization', 'LOCALITY149_CENTRALIZATION_STATE_UNPROVEN'],
    ['two-properties', 'LOCALITY149_PROPERTY_AREA_QUALIFICATION_REQUIRED'],
    ['road', 'LOCALITY149_ROAD_EXTENT_ALLOCATION_REQUIRED'],
]);

it('rejects stale mismatched future or altered material records even when their envelope is rehashed', function (string $mutation, string $reason): void {
    $input = locality149Fixture();
    $location = $input['locations'][0]['evidence']['record'];
    switch ($mutation) {
        case 'operation': $location['operation_id'] = 'OTHER';
            break;
        case 'supply': $location['supply_id'] = 'OTHER';
            break;
        case 'date': $location['applicable_on'] = '2026-10-19';
            break;
        case 'future-proof': $location['observed_at'] = '2026-11-03T12:00:00.000001Z';
            break;
        case 'wrong-role': $location['role'] = 'ACQUIRER_DOMICILE';
            break;
        case 'wrong-document': $input['transport_document_id'] = 'OTHER';
            break;
        case 'wrong-hash': $input['scope_evidence']['record_hash'] = str_repeat('0', 64);
            break;
        case 'wrong-scope': $input['scope_evidence']['record']['service_category'] = 'PORT_SERVICE';
            $input['scope_evidence'] = locality149Envelope($input['scope_evidence']['record']);
            break;
        case 'uf-conflict': $input['locations'][0]['uf'] = 'RS';
            $location['uf'] = 'RS';
            break;
    }
    $input['locations'][0]['evidence'] = locality149Envelope($location);
    $report = locality149Qualifier()->qualify($input);
    expect($report['status'])->toBe('ACTION_REQUIRED')->and($report['reason'])->toBe($reason)
        ->and($report['locality'])->toBeNull();
})->with([
    ['operation', 'LOCALITY149_MATERIAL_LOCATION_BINDING_UNPROVEN'],
    ['supply', 'LOCALITY149_MATERIAL_LOCATION_BINDING_UNPROVEN'],
    ['date', 'LOCALITY149_MATERIAL_LOCATION_BINDING_UNPROVEN'],
    ['future-proof', 'LOCALITY149_MATERIAL_LOCATION_BINDING_UNPROVEN'],
    ['wrong-role', 'LOCALITY149_MATERIAL_LOCATION_BINDING_UNPROVEN'],
    ['wrong-document', 'LOCALITY149_CARGO_DOCUMENT_BINDING_UNPROVEN'],
    ['wrong-hash', 'LOCALITY149_SCOPE_RECORD_UNPROVEN'],
    ['wrong-scope', 'LOCALITY149_SCOPE_RECORD_UNPROVEN'],
    ['uf-conflict', 'LOCALITY149_GEOGRAPHY115_UF_CODE_PREFIX_CONFLICT'],
]);

it('rejects a rehashed report that silently replaces the proven special locality', function (): void {
    $report = locality149Qualifier()->qualify(locality149Fixture());
    $report['locality']['municipality_ibge'] = '3509502';
    expect(locality149Qualifier()->replay($report))->toBeFalse();
    unset($report['report_hash']);
    $report['report_hash'] = CanonicalJson::hash($report);
    expect(locality149Qualifier()->replay($report))->toBeFalse();
});

it('binds the material records to the actual supplied parties instead of accepting a copied location proof', function (string $party): void {
    $input = locality149Fixture();
    $input[$party] = 'OTHER.PARTY';
    $result = locality149Qualifier()->qualify($input);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['locality'])->toBeNull()
        ->and($result['reason'])->toBe('LOCALITY149_SCOPE_RECORD_UNPROVEN');
})->with(['supplier_id', 'recipient_id']);

it('keeps source availability and operation dates separate from valid-looking locality records', function (string $key, string $value, string $reason): void {
    $input = locality149Fixture();
    $input[$key] = $value;
    $result = locality149Qualifier()->qualify($input);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe($reason)
        ->and($result['locality'])->toBeNull();
})->with([
    ['knowledge_at', '2026-11-03T11:18:56.999999Z', 'LOCALITY149_SOURCE_KNOWLEDGE_UNPROVEN'],
    ['operation_date', '2026-11-04', 'LOCALITY149_OPERATION_DATE_UNPROVEN'],
    ['operation_date', '2026-02-30', 'LOCALITY149_OPERATION_DATE_UNPROVEN'],
]);
