<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ServiceLocality149;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

/** @param array<string, mixed> $record
 * @return array<string, mixed>
 */
function locality149Envelope(array $record): array
{
    return ['source_id' => 'FIXTURE.MATERIAL.RECORD.149', 'record' => $record, 'record_hash' => CanonicalJson::hash($record)];
}

/** @return array<string, mixed> */
function locality149Fixture(string $category = 'CARGO_TRANSPORT'): array
{
    $rules = [
        'IMMOVABLE_RELATED' => ['II', 'PROPERTY_LOCATION'], 'PERSONAL_PRESENTIAL' => ['III', 'PERSONAL_PERFORMANCE'],
        'EVENT_ORGANIZATION' => ['IV', 'EVENT_LOCATION'], 'MOVABLE_PHYSICAL_SERVICE' => ['V', 'PHYSICAL_PERFORMANCE'],
        'PORT_SERVICE' => ['V', 'PORT_LOCATION'], 'PASSENGER_TRANSPORT' => ['VI', 'TRANSPORT_START'],
        'CARGO_TRANSPORT' => ['VII', 'DOCUMENTED_DELIVERY'], 'FIXED_COMMUNICATION' => ['IX', 'TERMINAL_INSTALLATION'],
    ];
    [$inciso, $role] = $rules[$category];
    $binding = ['operation_id' => 'OP149', 'supply_id' => 'SUPPLY149', 'applicable_on' => '2026-10-20',
        'supplier_id' => 'SUPPLIER149', 'recipient_id' => 'RECIPIENT149',
        'observed_at' => '2026-11-03T11:30:00Z'];
    $scope = [...$binding, 'service_category' => $category, 'source_article' => 'LC214 art.11 '.$inciso,
        'scope_confirmed' => true, 'delivery_mode' => 'PRESENTIAL', 'partially_remote' => false, 'centralized_purchase' => false];
    $location = [...$binding, 'location_id' => 'LOCAL149', 'role' => $role, 'document_id' => 'DOC149',
        'uf' => 'SP', 'municipality_ibge' => '3550308'];

    return ['operation_id' => 'OP149', 'supply_id' => 'SUPPLY149', 'supplier_id' => 'SUPPLIER149',
        'recipient_id' => 'RECIPIENT149', 'operation_date' => '2026-10-20', 'knowledge_at' => '2026-11-03T12:00:00Z',
        'service_category' => $category, 'transport_document_id' => 'DOC149', 'scope_evidence' => locality149Envelope($scope),
        'locations' => [['id' => 'LOCAL149', 'uf' => 'SP', 'municipality_ibge' => '3550308', 'evidence' => locality149Envelope($location)]]];
}

function locality149Qualifier(): ServiceLocality149
{
    $root = dirname(__DIR__, 2).'/resources/fiscal/';
    $codes = json_decode((string) file_get_contents($root.'catalogs/rtc-codes-2026.10.27-141-draft1.json'), true, 512, JSON_THROW_ON_ERROR);

    return new ServiceLocality149($codes['observed_geographic_reference'], $codes['observed_geographic_reference_policy']);
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    $directory = dirname(__DIR__, 2).'/storage/app/agent-a-A149';
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
    $input = locality149Fixture();
    file_put_contents($directory.'/cargo.json', json_encode($input, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $input['transport_document_id'] = 'OTHER.DOC';
    file_put_contents($directory.'/wrong-document.json', json_encode($input, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $rows = [];
    foreach (['IMMOVABLE_RELATED', 'PERSONAL_PRESENTIAL', 'EVENT_ORGANIZATION', 'MOVABLE_PHYSICAL_SERVICE',
        'PORT_SERVICE', 'PASSENGER_TRANSPORT', 'CARGO_TRANSPORT', 'FIXED_COMMUNICATION'] as $category) {
        $report = locality149Qualifier()->qualify(locality149Fixture($category));
        $rows[] = ['category' => $category, 'result' => $report, 'replay' => locality149Qualifier()->replay($report)];
    }
    echo json_encode(['synthetic_material_records' => true, 'families' => $rows], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
}
