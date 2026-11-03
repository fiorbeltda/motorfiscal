<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ServiceLocality149;

require_once __DIR__.'/locality149_fixture.php';

/** @return array<string, mixed> */
function locality150Fixture(string $kind = 'property'): array
{
    $property = $kind === 'property';
    $input = locality149Fixture($property ? 'IMMOVABLE_RELATED' : 'CARGO_TRANSPORT');
    unset($input['transport_document_id']);
    $category = $property ? 'IMMOVABLE_RELATED' : 'ROAD_EXPLOITATION';
    $asset = $property ? 'PROPERTY150' : 'ROAD150';
    $unit = $property ? 'M2' : 'M';
    $total = $property ? '100.00' : '3';
    $input['service_category'] = $category;
    $scope = $input['scope_evidence']['record'];
    $scope['asset_id'] = $asset;
    $scope['service_category'] = $category;
    $scope['source_article'] = $property ? 'LC214 art.11 II' : 'LC214 art.11 VIII';
    $scope['paid_exploitation'] = ! $property;
    $input['scope_evidence'] = locality149Envelope($scope);
    $template = $input['locations'][0]['evidence']['record'];
    $specs = $property
        ? [['part-1', 'SP', '3550308', '30.25'], ['part-2', 'SP', '3550308', '30.25'], ['part-3', 'SP', '3509502', '39.50']]
        : [['segment-1', 'SP', '3550308', '1'], ['segment-2', 'SP', '3509502', '1'], ['segment-3', 'RJ', '3304557', '1']];
    $input['locations'] = [];
    foreach ($specs as [$id, $uf, $municipality, $measure]) {
        $record = [...$template, 'location_id' => $id, 'role' => $property ? 'PROPERTY_AREA' : 'ROAD_EXTENT',
            'asset_id' => $asset, 'document_id' => 'SURVEY150', 'unit' => $unit, 'measure' => $measure,
            'uf' => $uf, 'municipality_ibge' => $municipality];
        $input['locations'][] = ['id' => $id, 'uf' => $uf, 'municipality_ibge' => $municipality,
            'measure' => $measure, 'evidence' => locality149Envelope($record)];
    }
    $coverageBinding = array_intersect_key($template, array_flip(['operation_id', 'supply_id', 'supplier_id', 'recipient_id', 'applicable_on', 'observed_at']));
    $coverage = [...$coverageBinding, 'role' => 'TERRITORIAL_MEASUREMENT_COVERAGE',
        'asset_id' => $asset, 'document_id' => 'SURVEY150', 'unit' => $unit,
        'total' => $total, 'complete' => true, 'location_ids' => array_column($input['locations'], 'id')];
    $input['territorial_measurements'] = ['contract' => ServiceLocality149::MEASURED_CONTRACT,
        'asset_id' => $asset, 'unit' => $unit, 'total' => $total, 'complete' => true,
        'evidence' => locality149Envelope($coverage)];

    return $input;
}

/** Change declared and recorded quantities together for an explicitly supplied synthetic survey.
 * @param  array<string, mixed>  $input
 * @param  list<string>  $values
 * @return array<string, mixed>
 */
function locality150Measures(array $input, array $values, string $total): array
{
    foreach ($values as $index => $value) {
        $input['locations'][$index]['measure'] = $value;
        $input['locations'][$index]['evidence']['record']['measure'] = $value;
        $input['locations'][$index]['evidence'] = locality149Envelope($input['locations'][$index]['evidence']['record']);
    }
    $input['territorial_measurements']['total'] = $total;
    $input['territorial_measurements']['evidence']['record']['total'] = $total;
    $input['territorial_measurements']['evidence'] = locality149Envelope($input['territorial_measurements']['evidence']['record']);

    return $input;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    $directory = dirname(__DIR__, 2).'/storage/app/agent-a-A150';
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }
    $rows = [];
    foreach (['property', 'road'] as $kind) {
        $input = locality150Fixture($kind);
        file_put_contents($directory.'/'.$kind.'.json', json_encode($input, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $result = locality149Qualifier()->qualify($input);
        $rows[] = ['case' => $kind, 'report' => $result, 'replay' => locality149Qualifier()->replay($result)];
    }
    $precise = locality150Measures(locality150Fixture(), ['1.00000000000000000001', '1', '2.00000000000000000000'], '4.00000000000000000001');
    $preciseReport = locality149Qualifier()->qualify($precise);
    $rows[] = ['case' => 'property_high_precision', 'report' => $preciseReport, 'replay' => locality149Qualifier()->replay($preciseReport)];
    $tie = locality150Measures(locality150Fixture(), ['25', '25', '50'], '100');
    file_put_contents($directory.'/property-tie.json', json_encode($tie, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode(['synthetic_surveys' => true, 'observations' => $rows], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
}
