<?php

declare(strict_types=1);

require __DIR__.'/build_phase4b_price112_scenarios.php';

use App\Modules\Fiscal\ConsumptionReform\DatedDocumentFactProjection108;
use App\Modules\Fiscal\ConsumptionReform\DocumentPriceFactProjection112;
use App\Modules\Fiscal\ConsumptionReform\PartialExportContractPrices110;
use App\Modules\Fiscal\ConsumptionReform\PartialExportDatedDpsIdentity107;
use App\Modules\Fiscal\ConsumptionReform\PartialExportDocumentPrices112;
use App\Modules\Fiscal\ConsumptionReform\PartialExportIssDatedResults106;
use App\Modules\Fiscal\ConsumptionReform\PartialExportIssPriceComposition111;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

$root = dirname(__DIR__);
$seedPath = '/tmp/geographic116-upstream-source-seed-v1.json';
$seed = json_decode((string) file_get_contents($seedPath), true, 512, JSON_THROW_ON_ERROR);
$factory = new FiscalEngineFactory;
$knowledge = $factory->captureKnowledge($root.'/resources/fiscal', $seed['target_bundle']);
$oldKnowledge = $factory->captureKnowledge($root.'/resources/fiscal', $seed['source_bundle']);
$load = fn (array $s) => $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($s)->input->context->facts;
$policy = PartialExportDocumentPrices112::policy();
$datedPolicy = $policy['composition_policy']['price_policy']['dated_identity_policy']['dated_results_policy'];
$identityPolicy = $policy['composition_policy']['price_policy']['dated_identity_policy'];
$prepared = [];
foreach ($seed['cases'] as $entry) {
    $sourcePath = $root.'/'.$entry['path'];
    if (hash_file('sha256', $sourcePath) !== $entry['sha256']) {
        throw new RuntimeException('Immutable108 source changed:'.$entry['name']);
    }
    $original = json_decode((string) file_get_contents($sourcePath), true, 512, JSON_THROW_ON_ERROR);
    $scenario = $original;
    $scenario['scenario_id'] = 'GEOGRAPHIC116-UPSTREAM-'.$entry['name'];
    $scenario['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $scenario['catalog_versions'] = $knowledge['catalog_versions'];
    $scenario['knowledge_at'] = $seed['synthetic_knowledge_at'];
    $facts = $original['facts'];
    $facts['rtc.assess_export_dated_documents'] = false;
    $facts['rtc.assess_export_document_prices'] = true;
    $material = $facts['rtc.export_iss_material_records'];
    $results = $facts['rtc.export_iss_dated_result_records'];
    $identities = $facts['rtc.export_dated_dps_identity_records'];
    $materialByRole = array_column($material, null, 'role');
    $prices = [];
    $compositions = [];
    foreach (['RTC_FOREIGN' => '350.00', 'RTC_DOMESTIC' => '650.00'] as $role => $amount) {
        $performance = $materialByRole[$role]['performance'] ?? [];
        $documentReference = $facts[$role === 'RTC_FOREIGN' ? 'rtc.export_foreign_document_id' : 'rtc.export_domestic_document_id'];
        $price = ['role' => $role, 'document_reference' => $documentReference,
            'contract_reference' => $performance['contract_reference'] ?? 'SYNTHETIC-UNBOUND-CONTRACT112',
            'unit_reference' => $performance['unit_reference'] ?? 'SYNTHETIC-UNBOUND-UNIT112',
            'line_reference' => 'SYNTHETIC-NEW-PRICE112-LINE-'.$role, 'price_currency' => 'BRL',
            'service_price' => $amount, 'price_semantics' => 'CONTRACTUAL_SERVICE_PRICE_NOT_TAX_BASE'];
        $prices[] = $price;
        $compositions[] = ['role' => $role, 'document_reference' => $documentReference, 'contract_reference' => $price['contract_reference'],
            'unit_reference' => $price['unit_reference'], 'line_reference' => $price['line_reference'], 'currency' => 'BRL',
            'principal' => $amount, 'service_additions' => [], 'unconditional_discounts' => [], 'conditional_discounts' => [],
            'price110_amount_basis' => 'GROSS_BEFORE_ALL_DISCOUNTS', 'all_price_adjustments_enumerated' => true];
    }
    $facts[DocumentPriceFactProjection112::PRICE_RECORDS] = $prices;
    $facts[DocumentPriceFactProjection112::COMPOSITION_RECORDS] = $compositions;
    $facts[DocumentPriceFactProjection112::PRICE_PROOFS] = [['unbound_upstream' => 'NO_PRICE_APPROVAL_WITHOUT_FRESH_PREDECESSORS']];
    $facts[DocumentPriceFactProjection112::COMPOSITION_PROOFS] = [['unbound_upstream' => 'NO_COMPOSITION_APPROVAL_WITHOUT_FRESH_PREDECESSORS']];
    $scenario['facts'] = $facts;
    $row = ['name' => 'upstream108-'.$entry['name'], 'scenario' => $scenario, 'upstream108_path' => $entry['path'],
        'upstream108_sha256' => $entry['sha256'], 'source_rtc_snapshot_path' => $entry['source_rtc_snapshot_path'],
        'source_rtc_snapshot_sha256' => $entry['source_rtc_snapshot_sha256'],
        'captured108_nominal_stage' => $entry['captured108_nominal_stage'], 'captured108_fx_stage' => $entry['captured108_fx_stage'],
        'rtc_gross_currency' => $entry['rtc_gross_currency'], 'source_dated_status' => $entry['dated_status'],
        'source_dated_reason' => $entry['dated_reason'], 'new_price_currency' => 'BRL',
        'new_prices_are_independent_synthetic_contract_claims_not_rtc_allocation' => true,
        'expected_iss_prices' => [], 'expected_payment_candidates' => [], 'expected_iss_dates' => [],
        'identity_rebinding_changes' => [], 'status' => $entry['status'], 'price_status' => null, 'composition_status' => null];
    if ($entry['dated_status'] === null) {
        $row['reason'] = match ($entry['reason']) {
            'RTC_EXPORT_DATED108_PARTIAL_EXPORT_ROUTE_REQUIRED' => 'RTC_EXPORT_PRICE112_PARTIAL_EXPORT_ROUTE_REQUIRED',
            'RTC_EXPORT_DATED108_SEPARATE_TEMPORAL_CONTEXT_REQUIRED' => 'RTC_EXPORT_PRICE112_SEPARATE_TEMPORAL_CONTEXT_REQUIRED',
            'RTC_EXPORT_DATED108_SEPARATE_LEGACY_CONTEXT_REQUIRED' => 'RTC_EXPORT_PRICE112_SEPARATE_LEGACY_CONTEXT_REQUIRED',
            default => $entry['reason'],
        };
        $prepared[] = $row;

        continue;
    }
    $context = DocumentPriceFactProjection112::from($load($scenario))['facts'];
    $oldBag = $factory->scenarioLoaderFromKnowledge($oldKnowledge)->fromArray($original)->input->context->facts;
    $oldContext = DatedDocumentFactProjection108::from($oldBag)['facts'];
    $oldDated = (new PartialExportIssDatedResults106)->qualify($oldContext, $material, $results, $original['knowledge_at'], $datedPolicy);
    $dated = (new PartialExportIssDatedResults106)->qualify($context, $material, $results, $scenario['knowledge_at'], $datedPolicy);
    if (($dated['dated_material_and_results_locally_bound'] ?? false) === true) {
        if (($oldDated['dated_material_and_results_locally_bound'] ?? false) !== true) {
            throw new RuntimeException('A formerly refused result changed at new knowledge;review before transport:'.$entry['name']);
        }
        $newByRole = array_column($dated['documents'], null, 'role');
        $oldByRole = array_column($oldDated['documents'], null, 'role');
        foreach ($identities as $i => &$record) {
            $before = $record['proof'];
            $proof = &$record['proof'];
            $role = $record['role'];
            $wasValid = isset($before['source_hash']) && $before['source_hash'] === CanonicalJson::hash(array_diff_key($before, ['source_hash' => true]));
            if (($proof['dated_pair_binding_hash'] ?? null) === $oldDated['binding_hash']) {
                $proof['dated_pair_binding_hash'] = $dated['binding_hash'];
            }
            if (isset($oldByRole[$role],$newByRole[$role]) && ($proof['dated_document_hash'] ?? null) === CanonicalJson::hash($oldByRole[$role])) {
                $proof['dated_document_hash'] = CanonicalJson::hash($newByRole[$role]);
            }
            if (($proof['observed_at'] ?? null) === $original['knowledge_at']) {
                $proof['observed_at'] = $scenario['knowledge_at'];
            }
            if ($wasValid) {
                $proof = price112Seal($proof);
            }
            $changed = [];
            foreach ($proof as $key => $value) {
                if (! array_key_exists($key, $before) || $before[$key] !== $value) {
                    $changed[] = $key;
                }
            }
            $row['identity_rebinding_changes'][] = ['index' => $i, 'role' => $role, 'source_hash_was_valid' => $wasValid,
                'changed_fields' => $changed, 'old_proof_hash' => $before['source_hash'], 'new_proof_hash' => $proof['source_hash']];
            unset($proof);
        }unset($record);
    }
    $scenario['facts']['rtc.export_dated_dps_identity_records'] = $identities;
    $identity = (new PartialExportDatedDpsIdentity107)->qualify($context, $material, $results, $identities, $scenario['knowledge_at'], $identityPolicy);
    if ($identity['status'] !== $entry['dated_status'] || $identity['reason'] !== $entry['dated_reason']) {
        throw new RuntimeException('Original dated refusal/qualification changed:'.$entry['name'].' '.json_encode($identity));
    }
    if ($entry['dated_status'] !== 'LOCAL_DATED_DPS_IDENTITY_PAIR_BOUND') {
        $row['scenario'] = $scenario;
        $row['price_status'] = $entry['dated_status'];
        $row['reason'] = 'PRICE110_FRESH_DATED_IDENTITY_PAIR_REQUIRED';
        $row['expected_price_dated_reason'] = $entry['dated_reason'];
        $adapter = (new PartialExportDocumentPrices112)->qualify($load($scenario), new DateTimeImmutable($scenario['knowledge_at']), $policy);
        if ($adapter['status'] !== $row['status'] || $adapter['reason'] !== $row['reason']) {
            throw new RuntimeException('Refusal not preserved:'.$entry['name']);
        }
        $prepared[] = $row;

        continue;
    }
    $priceByRole = array_column($prices, null, 'role');
    $documents = [];
    $dates = [];
    foreach ($identity['documents'] as $d) {
        $dates[] = $d['iss_supply_on'];
        $documents[] = [...$priceByRole[$d['role']],
            'iss_supply_on' => $d['iss_supply_on'], 'dps_id' => $d['dps_id'], 'identity_hash' => CanonicalJson::hash($d['identity']),
            'dated_document_hash' => $d['dated_document_hash']];
    }
    $priceProof = price112Capture(['contract' => PartialExportContractPrices110::CONTRACT, 'kind' => 'CAPTURED_CONTRACT_PRICE_PAIR',
        'evidence_id' => 'SYNTHETIC-GEOGRAPHIC116-UPSTREAM-CONTRACT-'.$entry['name'], 'observed_at' => $scenario['knowledge_at'],
        'valid_from' => min($dates), 'valid_to' => max($dates), 'revoked_at' => null, 'superseded_at' => null,
        'value' => ['operation_reference' => $facts['rtc.export_operation_reference'], 'transaction_evidence_id' => $facts['rtc.export_transaction_evidence_id'],
            'supplier_id' => $facts['rtc.supplier_id'], 'acquirer_id' => $facts['rtc.acquirer_id'], 'supplier_municipality' => $facts['provider.municipality_ibge'],
            'dated_identity_binding_hash' => $identity['binding_hash'], 'contract_price_currency' => 'BRL', 'contract_service_price_total' => '1000.00',
            'rtc_price_relationship' => 'SEPARATE_PRICE_BASIS_UNRECONCILED_WITH_RTC', 'documents' => $documents]],
        'synthetic://geographic116/upstream/contract/'.$entry['name']);
    $scenario['facts'][DocumentPriceFactProjection112::PRICE_PROOFS] = [$priceProof];
    $price = (new PartialExportContractPrices110)->qualify($context, $material, $results, $identities, $prices, $priceProof, $scenario['knowledge_at'], $policy['composition_policy']['price_policy']);
    if (($price['contract_price_evidence_locally_bound'] ?? false) !== true) {
        throw new RuntimeException('New synthetic price failed:'.$entry['name'].' '.$price['reason']);
    }
    $byRole = array_column($compositions, null, 'role');
    $documents = [];
    foreach ($price['documents'] as $d) {
        $amount = $d['service_price'];
        $documents[] = [...$d, 'composition' => $byRole[$d['role']],
            'gross_service_price' => $amount, 'unconditional_discount' => '0.00', 'conditional_discount' => '0.00',
            'price_for_iss_evaluation_in_input_currency' => $amount, 'payment_candidate_if_conditional_terms_satisfied' => $amount];
    }
    $compositionProof = price112Capture(['contract' => PartialExportIssPriceComposition111::CONTRACT, 'kind' => 'CAPTURED_DOCUMENT_PRICE_COMPOSITION_PAIR',
        'evidence_id' => 'SYNTHETIC-GEOGRAPHIC116-UPSTREAM-COMPOSITION-'.$entry['name'], 'observed_at' => $scenario['knowledge_at'],
        'valid_from' => min($dates), 'valid_to' => max($dates), 'revoked_at' => null, 'superseded_at' => null,
        'value' => ['price110_binding_hash' => $price['binding_hash'], 'operation_reference' => $facts['rtc.export_operation_reference'],
            'supplier_id' => $facts['rtc.supplier_id'], 'acquirer_id' => $facts['rtc.acquirer_id'],
            'supplier_municipality' => $facts['provider.municipality_ibge'], 'documents' => $documents]],
        'synthetic://geographic116/upstream/composition/'.$entry['name']);
    $scenario['facts'][DocumentPriceFactProjection112::COMPOSITION_PROOFS] = [$compositionProof];
    if (DocumentPriceFactProjection112::from($load($scenario))['facts'] !== $context) {
        throw new RuntimeException('Unexpected proof cycle');
    }
    $adapter = (new PartialExportDocumentPrices112)->qualify($load($scenario), new DateTimeImmutable($scenario['knowledge_at']), $policy);
    if ($adapter['status'] !== 'LOCAL_ISS_DOCUMENT_PRICES_COMPOSED') {
        throw new RuntimeException('Composition failed:'.$entry['name'].' '.json_encode($adapter));
    }
    $row['scenario'] = $scenario;
    $row['reason'] = 'RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN';
    $row['price_status'] = 'LOCAL_CONTRACT_PRICE_PAIR_EVIDENCE_BOUND';
    $row['composition_status'] = 'LOCAL_ISS_DOCUMENT_PRICES_COMPOSED';
    $row['expected_iss_prices'] = ['350.00', '650.00'];
    $row['expected_payment_candidates'] = ['350.00', '650.00'];
    $row['expected_iss_dates'] = $dates;
    $row['qualification_facts_hash'] = CanonicalJson::hash($context);
    $prepared[] = $row;
}
if (count($prepared) !== 50) {
    throw new RuntimeException('All50 remaining original sources required');
}
$directory = $root.'/resources/fiscal/scenarios/geographic-prices-upstream-2026.10.20-116-v1';
if (is_dir($directory)) {
    throw new RuntimeException('Exclusive upstream112 scenario directory already exists');
}
mkdir($directory);
$manifest = [];
foreach ($prepared as $i => $row) {
    $name = preg_replace('/[^a-z0-9-]/', '-', strtolower($row['name']));
    $path = 'resources/fiscal/scenarios/geographic-prices-upstream-2026.10.20-116-v1/'.sprintf('%03d', $i).'-'.$name.'.json';
    $handle = fopen($root.'/'.$path, 'x');
    fwrite($handle, json_encode($row['scenario'], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    fclose($handle);
    unset($row['scenario']);
    $manifest[] = [...$row, 'path' => $path, 'scenario_sha256' => hash_file('sha256', $root.'/'.$path)];
}
$target = $root.'/tests/Fixtures/geographic116-price-upstream-expectations.json';
$handle = fopen($target, 'x');
fwrite($handle, json_encode(['cases' => $manifest, 'bundle' => $seed['target_bundle'], 'source_seed_sha256' => hash_file('sha256', $seedPath),
    'synthetic_knowledge_at' => $seed['synthetic_knowledge_at'], 'all58original108_sources_now_represented_with_initial8' => true,
    'native_execution_still_required' => true, 'production_ready' => false], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
fclose($handle);
echo json_encode(['scenarios' => count($manifest), 'positive_compositions' => count(array_filter($manifest, fn (array $r): bool => $r['composition_status'] === 'LOCAL_ISS_DOCUMENT_PRICES_COMPOSED')),
    'manifest_sha256' => hash_file('sha256', $target), 'production_ready' => false], JSON_THROW_ON_ERROR);
