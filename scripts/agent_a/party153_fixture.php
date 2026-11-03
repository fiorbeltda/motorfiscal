<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\EntityDomicile151;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

require_once __DIR__.'/domicile151_fixture.php';

/** @return array<string, mixed> */
function party153Address(string $uf = 'SP', bool $municipalOnly = false): array
{
    return ['in_brazil' => true, 'uf' => $uf, 'municipality_ibge' => $uf === 'SP' ? '3550308' : '3304557',
        'granularity' => $municipalOnly ? 'MUNICIPALITY' : 'ADDRESS',
        'components' => $municipalOnly ? [] : ['street' => 'SYNTHETIC.STREET.'.$uf, 'number' => '10',
            'postal_code' => $uf === 'SP' ? '01000000' : '20000000']];
}

/** @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function party153Rebind(array $input): array
{
    $bind = static function (array $record) use ($input): array {
        foreach (['operation_id', 'supply_id', 'supplier_id', 'acquirer_id', 'recipient_id', 'onerous',
            'acquirer_domiciled_in_brazil', 'recipient_domiciled_in_brazil'] as $key) {
            $record[$key] = $input[$key];
        }
        $record['applicable_on'] = $input['operation_date'];
        $record['observed_at'] ??= '2026-11-03T17:00:00Z';

        return ['source_id' => 'SYNTHETIC.MATERIAL.PARTY153', 'record' => $record, 'record_hash' => CanonicalJson::hash($record)];
    };
    $input['scope_evidence'] = $bind($input['scope_evidence']['record']);
    foreach (['registration_evidence', 'material_evidence', 'selection_evidence', 'impossibility_evidence'] as $key) {
        if (isset($input['party_domicile_qualification'][$key]['record'])) {
            $input['party_domicile_qualification'][$key] = $bind($input['party_domicile_qualification'][$key]['record']);
        }
    }
    foreach ($input['party_domicile_qualification']['criteria'] ?? [] as $index => $envelope) {
        $input['party_domicile_qualification']['criteria'][$index] = $bind($envelope['record']);
    }

    return $input;
}

/** @return array<string, mixed> */
function party153Criterion(string $id, string $kind, string $party, string $uf = 'SP'): array
{
    return ['record' => ['criterion_id' => $id, 'role' => 'DOMICILE_CRITERION', 'party_id' => $party,
        'kind' => $kind, 'document_id' => 'SYNTHETIC.CRITERION.'.$id,
        'address' => party153Address($uf, $kind === 'IP_GEOLOCATION'), 'declared_to_supplier' => true,
        'collected_in_operation' => true, 'payment_arrangement_id' => 'SYNTHETIC.ARRANGEMENT153',
        'device_location_method' => 'GEOLOCATION', 'acquisition_device_evidence_id' => 'SYNTHETIC.DEVICE153']];
}

/** @param list<array<string, mixed>> $criteria
 * @return array<string, mixed>
 */
function party153Impossibility(array $criteria, string $party): array
{
    $availability = [];
    foreach (['DECLARED_ADDRESS', 'COMMERCIAL_INFO', 'PAYMENT_ARRANGEMENT', 'IP_GEOLOCATION'] as $kind) {
        $ids = [];
        foreach ($criteria as $envelope) {
            if ($envelope['record']['kind'] === $kind) {
                $ids[] = $envelope['record']['criterion_id'];
            }
        }
        $availability[] = ['kind' => $kind, 'available' => $ids !== [], 'available_criterion_ids' => $ids,
            'unavailability_reason' => 'SYNTHETIC.OBSERVED.UNAVAILABILITY', 'unavailability_evidence_id' => 'SYNTHETIC.UNAVAILABLE.'.$kind];
    }

    return ['record' => ['role' => 'CRITERIA_COMBINATION_IMPOSSIBILITY', 'party_id' => $party,
        'document_id' => 'SYNTHETIC.IMPOSSIBILITY153', 'criteria_coverage_complete' => true, 'availability' => $availability]];
}

/** @return array<string, mixed> */
function party153Fixture(string $variant = 'home'): array
{
    $input = domicile151Fixture(match ($variant) {
        'foreign-recipient' => 'foreign-acquirer', 'nononerous' => 'nononerous', 'remote' => 'remote-personal', default => 'domestic',
    });
    unset($input['registry_evidence'], $input['supply_establishment_evidence']);
    $input['operation_id'] = 'OP153';
    $input['supply_id'] = 'SUPPLY153';
    $input['knowledge_at'] = '2026-11-03T17:05:00Z';
    $party = in_array($variant, ['foreign-recipient', 'nononerous'], true) ? $input['recipient_id'] : $input['acquirer_id'];
    $unregistered = in_array($variant, ['combined', 'geo-combined', 'fallback', 'conflicting-fallback', 'unregistered-business'], true);
    $economic = in_array($variant, ['no-home', 'multiple-homes'], true);
    $registry = ['role' => 'PARTY_REGISTRATION_STATE', 'party_id' => $party,
        'party_kind' => $variant === 'unregistered-business' ? 'LEGAL_ENTITY' : 'NATURAL_PERSON',
        'country' => 'BR', 'document_id' => 'SYNTHETIC.REGISTRATION153', 'regularly_registered' => ! $unregistered,
        'unique_registration' => true, 'natural_person_subject_to_registration105' => false,
        'valid_from' => '2026-01-01', 'valid_until' => null, 'domicile_address' => party153Address($economic ? 'RJ' : 'SP')];
    $home = ['id' => 'HOME153', 'valid_from' => '2026-01-01', 'valid_until' => null, 'address' => party153Address()];
    $material = ['role' => 'PERSONAL_DOMICILE_MATERIAL_FACTS', 'party_id' => $party, 'document_id' => 'SYNTHETIC.PERSONAL153',
        'complete' => true, 'no_permanent_home' => $variant === 'no-home', 'permanent_homes' => $variant === 'no-home' ? [] : [$home],
        'economic_relation_location' => party153Address('RJ'), 'economic_relevance_qualified' => true,
        'economic_relevance_qualification_reference' => 'SYNTHETIC.ECONOMIC.ASSESSMENT153'];
    if ($variant === 'multiple-homes') {
        $material['permanent_homes'][] = [...$home, 'id' => 'HOME153RJ', 'address' => party153Address('RJ')];
    }
    $qualification = ['contract' => EntityDomicile151::PARTY_CONTRACT,
        'method' => 'REGISTERED_NATURAL_PERSON', 'registration_evidence' => ['record' => $registry],
        'material_evidence' => ['record' => $material]];
    if ($unregistered) {
        unset($qualification['material_evidence']);
        $declared = party153Criterion('declared153', 'DECLARED_ADDRESS', $party);
        $commercial = party153Criterion('commercial153', 'COMMERCIAL_INFO', $party, $variant === 'conflicting-fallback' ? 'RJ' : 'SP');
        $geo = party153Criterion('geo153', 'IP_GEOLOCATION', $party, $variant === 'combined' ? 'RJ' : 'SP');
        $qualification['criteria'] = match ($variant) {
            'fallback' => [$declared], 'conflicting-fallback' => [$declared, $commercial],
            'geo-combined' => [$declared, $geo], default => [$declared, $commercial, $geo],
        };
        if (in_array($variant, ['fallback', 'conflicting-fallback'], true)) {
            $qualification['method'] = 'UNREGISTERED_DECLARED_FALLBACK';
            $qualification['impossibility_evidence'] = party153Impossibility($qualification['criteria'], $party);
        } else {
            $qualification['method'] = 'UNREGISTERED_COMBINED_CRITERIA';
            $qualification['selection_evidence'] = ['record' => ['role' => 'SUPPLIER_DOMICILE_CRITERIA_SELECTION',
                'party_id' => $party, 'supplier_selection_confirmed' => true, 'document_id' => 'SYNTHETIC.SELECTION153',
                'selected_criterion_ids' => ['declared153', $variant === 'geo-combined' ? 'geo153' : 'commercial153']]];
        }
    }
    $input['party_domicile_qualification'] = $qualification;

    return party153Rebind($input);
}

/** @return array<string, mixed> */
function party153ServiceFixture(): array
{
    $input = domicile151ServiceFixture();
    $party = party153Fixture('remote');
    foreach (['operation_id', 'supply_id', 'supplier_id', 'recipient_id', 'operation_date', 'knowledge_at'] as $key) {
        $input[$key] = $party[$key];
    }
    $scope = $input['scope_evidence']['record'];
    foreach (['operation_id', 'supply_id', 'supplier_id', 'recipient_id'] as $key) {
        $scope[$key] = $input[$key];
    }
    $scope['observed_at'] = '2026-11-03T17:00:00Z';
    $input['scope_evidence'] = domicile151Envelope($scope);
    $input['entity_domicile_qualification'] = domicile151Qualifier()->qualify($party);

    return $input;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    $directory = dirname(__DIR__, 2).'/storage/app/agent-a-A153';
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
    $cases = [];
    foreach (['home', 'no-home', 'multiple-homes', 'combined', 'geo-combined', 'fallback', 'conflicting-fallback', 'foreign-recipient', 'nononerous', 'unregistered-business'] as $variant) {
        $report = domicile151Qualifier()->qualify(party153Fixture($variant));
        $cases[] = ['case' => $variant, 'report' => $report, 'replay' => domicile151Qualifier()->replay($report)];
    }
    $inputs = ['home' => party153Fixture(), 'combined' => party153Fixture('combined'), 'fallback' => party153Fixture('fallback'),
        'service' => party153ServiceFixture()];
    $invalid = party153Fixture('fallback');
    unset($invalid['party_domicile_qualification']['impossibility_evidence']);
    $inputs['invalid'] = $invalid;
    foreach ($inputs as $name => $input) {
        file_put_contents($directory.'/'.$name.'.json', json_encode($input, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
    echo json_encode(['synthetic_personal_records' => true, 'cases' => $cases], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
}
