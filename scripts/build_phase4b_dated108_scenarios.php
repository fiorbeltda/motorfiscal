<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use App\Modules\Fiscal\ConsumptionReform\DatedDocumentFactProjection108;
use App\Modules\Fiscal\ConsumptionReform\PartialExportIssDatedResults106;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

$root = dirname(__DIR__);
$evidence = '/tmp/dated108-scenario-seed.json';
$seed = json_decode((string) file_get_contents($evidence), true, 512, JSON_THROW_ON_ERROR);
$factory = new FiscalEngineFactory;
$knowledge = $factory->captureKnowledge($root.'/resources/fiscal', $seed['source_bundle']);
$template = json_decode((string) file_get_contents($root.'/resources/fiscal/scenarios/dps-identity-2026.10.19-104-draft1/local-pair.json'), true, 512, JSON_THROW_ON_ERROR);
$directory = $root.'/resources/fiscal/scenarios/dated-documents-2026.10.19-108-draft3';
if (is_dir($directory)) {
    throw new RuntimeException('Exclusive scenario directory already exists');
}
mkdir($directory);
$known = new DateTimeImmutable;
$manifest = [];
foreach ($seed['cases'] as $selection) {
    $case = $selection['source_case'];
    $scenario = $template;
    $scenario['scenario_id'] = 'DATED108-'.$selection['name'];
    $scenario['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $scenario['catalog_versions'] = $knowledge['catalog_versions'];
    $scenario['knowledge_at'] = $known->format('Y-m-d\TH:i:s.uP');
    $facts = $case['facts'];
    foreach (['rtc.assess_export_iss_results', 'rtc.assess_export_sp_results', 'rtc.assess_export_dps_identity'] as $key) {
        $facts[$key] = false;
    }
    $facts['rtc.assess_export_dated_documents'] = true;
    $facts['rtc.export_iss_material_records'] = $case['material_records'];
    $facts['rtc.export_iss_dated_result_records'] = $case['result_records'];
    $facts['provider.iss_regime_proof_records'] = [$facts['provider.iss_regime_proof']];
    unset($facts['provider.iss_regime_proof']);
    $scenario['facts'] = $facts;
    $input = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($scenario);
    $projection = DatedDocumentFactProjection108::from($input->input->context->facts)['facts'];
    $newDated = (new PartialExportIssDatedResults106)->qualify($projection, $case['material_records'],
        $case['result_records'], $scenario['knowledge_at'], $case['policy']['dated_results_policy']);
    if (($newDated['dated_material_and_results_locally_bound'] ?? false) !== true) {
        throw new RuntimeException('Expected dated preparation failed: '.$selection['name'].' / '.$newDated['reason']);
    }
    $oldDated = (new PartialExportIssDatedResults106)->qualify($case['facts'], $case['material_records'],
        $case['result_records'], $case['known_at'], $case['policy']['dated_results_policy']);
    $newByRole = array_column($newDated['documents'], null, 'role');
    $oldByRole = array_column($oldDated['documents'], null, 'role');
    $records = $case['identity_records'];
    foreach ($records as &$record) {
        $proof = &$record['proof'];
        $oldBody = $proof;
        unset($oldBody['source_hash']);
        $wasValidHash = $proof['source_hash'] === CanonicalJson::hash($oldBody);
        $role = $proof['role'];
        if ($proof['dated_pair_binding_hash'] === $oldDated['binding_hash']) {
            $proof['dated_pair_binding_hash'] = $newDated['binding_hash'];
        }
        if ($proof['dated_document_hash'] === CanonicalJson::hash($oldByRole[$role])) {
            $proof['dated_document_hash'] = CanonicalJson::hash($newByRole[$role]);
        }
        if ($proof['observed_at'] === $case['known_at']) {
            $proof['observed_at'] = $scenario['knowledge_at'];
        }
        if ($wasValidHash) {
            $body = $proof;
            unset($body['source_hash']);
            $proof['source_hash'] = CanonicalJson::hash($body);
        }
        unset($proof);
    }
    unset($record);
    $scenario['facts']['rtc.export_dated_dps_identity_records'] = $records;
    foreach ($selection['fact_mutations'] ?? [] as $key => $value) {
        $scenario['facts'][$key] = $value;
    }
    $name = preg_replace('/[^a-z0-9-]/', '-', strtolower($selection['name']));
    $relative = 'resources/fiscal/scenarios/dated-documents-2026.10.19-108-draft3/'.$name.'.json';
    $handle = fopen($root.'/'.$relative, 'x');
    fwrite($handle, json_encode($scenario, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fclose($handle);
    $manifest[] = ['name' => $selection['name'], 'path' => $relative, 'scenario_sha256' => hash_file('sha256', $root.'/'.$relative),
        'status' => $selection['status'], 'reason' => $selection['reason'], 'dated_status' => $selection['dated_status'],
        'dated_reason' => $selection['dated_reason'], 'expected_iss_dates' => array_column($newDated['documents'], 'iss_supply_on'),
        'rtc_operation_date' => $case['facts']['rtc.operation_date'], 'source_prepared107_case' => $case['name'],
        'original_business_facts_preserved' => true];
}
$manifestPath = $root.'/tests/Fixtures/dated108-scenario-expectations.json';
$handle = fopen($manifestPath, 'x');
fwrite($handle, json_encode(['cases' => $manifest, 'bundle' => $seed['source_bundle'],
    'source_seed_sha256' => hash_file('sha256', $evidence), 'full_upstream_corpus_complete' => false,
    'production_ready' => false], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
fclose($handle);
echo json_encode(['scenarios' => count($manifest), 'manifest_sha256' => hash_file('sha256', $manifestPath),
    'full_upstream_corpus_complete' => false], JSON_THROW_ON_ERROR);
