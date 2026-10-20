<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use App\Modules\Fiscal\ConsumptionReform\DocumentPriceFactProjection112;
use App\Modules\Fiscal\ConsumptionReform\PartialExportContractPrices110;
use App\Modules\Fiscal\ConsumptionReform\PartialExportDatedDpsIdentity107;
use App\Modules\Fiscal\ConsumptionReform\PartialExportDocumentPrices112;
use App\Modules\Fiscal\ConsumptionReform\PartialExportIssDatedResults106;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;

function price112Seal(array $proof): array
{
    unset($proof['source_hash']);
    $proof['source_hash'] = CanonicalJson::hash($proof);

    return $proof;
}

function price112Capture(array $proof, string $reference): array
{
    unset($proof['source_hash'], $proof['origin']);
    $content = CanonicalJson::encode($proof);
    $proof['origin'] = ['reference' => $reference, 'media_type' => 'application/json',
        'content' => $content, 'content_sha256' => hash('sha256', $content)];

    return price112Seal($proof);
}

/** Reissue only synthetic transport/mapping claims; all monetary records and material business facts stay unchanged. */
function price112Rebase(array $fixture, array $template, array $knowledge, FiscalEngineFactory $factory): array
{
    $case = $fixture['input'];
    $source = $case['input110'];
    $expected = $fixture['expected_result'];
    if ($expected['status'] !== 'LOCAL_ISS_DOCUMENT_PRICES_COMPOSED') {
        throw new RuntimeException('Only accepted111 positive seeds are rebased');
    }
    $scenario = $template;
    $scenario['scenario_id'] = 'PRICE112-'.$case['name'];
    $scenario['competence_date'] = $source['facts']['rtc.operation_date'];
    $scenario['knowledge_at'] = $case['known_at']; // Explicit synthetic19:00UTC;not an observed future capture.
    $scenario['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $scenario['catalog_versions'] = $knowledge['catalog_versions'];
    $facts = $source['facts'];
    foreach (['rtc.assess_export_dated_documents', 'rtc.assess_export_iss_results', 'rtc.assess_export_sp_results', 'rtc.assess_export_dps_identity'] as $key) {
        $facts[$key] = false;
    }
    $facts['rtc.assess_export_document_prices'] = true;
    $facts['provider.iss_regime_proof_records'] = [$facts['provider.iss_regime_proof']];
    unset($facts['provider.iss_regime_proof']);
    $facts['rtc.export_iss_material_records'] = $source['material_records'];
    $facts['rtc.export_iss_dated_result_records'] = $source['result_records'];
    $facts['rtc.export_dated_dps_identity_records'] = $source['identity_records'];
    $facts[DocumentPriceFactProjection112::PRICE_RECORDS] = $source['price_records'];
    $facts[DocumentPriceFactProjection112::COMPOSITION_RECORDS] = $case['records'];
    $scenario['facts'] = $facts;
    $load = fn (array $s) => $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($s)->input->context->facts;
    $context = DocumentPriceFactProjection112::from($load($scenario))['facts'];
    $policy = PartialExportDocumentPrices112::policy();
    if (CanonicalJson::hash($policy) !== CanonicalJson::hash($knowledge['documents']['rtc_code_catalog']['partial_export_document_prices_policy'])) {
        throw new RuntimeException('Exact112 catalog policy mismatch');
    }
    $dated = (new PartialExportIssDatedResults106)->qualify($context, $source['material_records'], $source['result_records'],
        $scenario['knowledge_at'], $policy['composition_policy']['price_policy']['dated_identity_policy']['dated_results_policy']);
    if (($dated['dated_material_and_results_locally_bound'] ?? false) !== true) {
        throw new RuntimeException('Fresh106 failed:'.$dated['reason']);
    }
    $byRole = array_column($dated['documents'], null, 'role');
    $identities = $source['identity_records'];
    foreach ($identities as &$record) {
        $record['proof']['dated_pair_binding_hash'] = $dated['binding_hash'];
        $record['proof']['dated_document_hash'] = CanonicalJson::hash($byRole[$record['role']]);
        $record['proof']['observed_at'] = $scenario['knowledge_at'];
        $record['proof'] = price112Seal($record['proof']);
    } unset($record);
    $scenario['facts']['rtc.export_dated_dps_identity_records'] = $identities;
    $identity = (new PartialExportDatedDpsIdentity107)->qualify($context, $source['material_records'], $source['result_records'],
        $identities, $scenario['knowledge_at'], $policy['composition_policy']['price_policy']['dated_identity_policy']);
    if (($identity['dated_identity_pair_locally_bound'] ?? false) !== true) {
        throw new RuntimeException('Fresh107 failed:'.$identity['reason']);
    }
    $pricesByRole = array_column($source['price_records'], null, 'role');
    $documents = [];
    foreach ($identity['documents'] as $doc) {
        $documents[] = [...$pricesByRole[$doc['role']], 'iss_supply_on' => $doc['iss_supply_on'], 'dps_id' => $doc['dps_id'],
            'identity_hash' => CanonicalJson::hash($doc['identity']), 'dated_document_hash' => $doc['dated_document_hash']];
    }
    $proof = $source['proof'];
    $proof['evidence_id'] = 'SYNTHETIC-PRICE112-CONTRACT-'.$case['name'];
    $proof['observed_at'] = $scenario['knowledge_at'];
    $proof['value']['dated_identity_binding_hash'] = $identity['binding_hash'];
    $proof['value']['documents'] = $documents;
    $proof = price112Capture($proof, 'synthetic://price112/contract/'.$case['name']);
    $scenario['facts'][DocumentPriceFactProjection112::PRICE_PROOFS] = [$proof];
    $price = (new PartialExportContractPrices110)->qualify($context, $source['material_records'], $source['result_records'],
        $identities, $source['price_records'], $proof, $scenario['knowledge_at'], $policy['composition_policy']['price_policy']);
    if (($price['contract_price_evidence_locally_bound'] ?? false) !== true) {
        throw new RuntimeException('Fresh110 failed:'.$price['reason']);
    }
    $oldByRole = array_column($expected['documents'], null, 'role');
    $records = array_column($case['records'], null, 'role');
    $documents = [];
    foreach ($price['documents'] as $doc) {
        $old = $oldByRole[$doc['role']];
        $documents[] = [...$doc, 'composition' => $records[$doc['role']], 'gross_service_price' => $old['gross_service_price'],
            'unconditional_discount' => $old['unconditional_discount'], 'conditional_discount' => $old['conditional_discount'],
            'price_for_iss_evaluation_in_input_currency' => $old['price_for_iss_evaluation_in_input_currency'],
            'payment_candidate_if_conditional_terms_satisfied' => $old['payment_candidate_if_conditional_terms_satisfied']];
    }
    $compositionProof = $case['proof'];
    $compositionProof['evidence_id'] = 'SYNTHETIC-PRICE112-COMPOSITION-'.$case['name'];
    $compositionProof['observed_at'] = $scenario['knowledge_at'];
    $compositionProof['value']['price110_binding_hash'] = $price['binding_hash'];
    $compositionProof['value']['documents'] = $documents;
    $compositionProof = price112Capture($compositionProof, 'synthetic://price112/composition/'.$case['name']);
    $scenario['facts'][DocumentPriceFactProjection112::COMPOSITION_PROOFS] = [$compositionProof];
    $finalFacts = $load($scenario);
    $finalContext = DocumentPriceFactProjection112::from($finalFacts)['facts'];
    if ($context !== $finalContext) {
        throw new RuntimeException('Proof carrier introduced a qualification hash cycle');
    }
    $adapter = (new PartialExportDocumentPrices112)->qualify($finalFacts, new DateTimeImmutable($scenario['knowledge_at']), $policy);
    if ($adapter['status'] !== 'LOCAL_ISS_DOCUMENT_PRICES_COMPOSED') {
        throw new RuntimeException('Adapter failed:'.json_encode($adapter));
    }

    return ['scenario' => $scenario, 'expected_iss_prices' => array_column($documents, 'price_for_iss_evaluation_in_input_currency'),
        'expected_payment_candidates' => array_column($documents, 'payment_candidate_if_conditional_terms_satisfied'),
        'expected_iss_dates' => array_column($documents, 'iss_supply_on'), 'source_case_name' => $case['name'],
        'source_fixture_canonical_sha256' => CanonicalJson::hash($fixture), 'qualification_facts_hash' => CanonicalJson::hash($context)];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    return;
}

$root = dirname(__DIR__);
$factory = new FiscalEngineFactory;
$bundle = 'bundles/consumption-phase4b-2026.10.19-112-draft2.json';
$knowledge = $factory->captureKnowledge($root.'/resources/fiscal', $bundle);
$template = json_decode((string) file_get_contents($root.'/resources/fiscal/scenarios/dps-identity-2026.10.19-104-draft1/local-pair.json'), true, 512, JSON_THROW_ON_ERROR);
$index = json_decode((string) file_get_contents($root.'/tests/Fixtures/price111-native112-index.json'), true, 512, JSON_THROW_ON_ERROR)['cases'];
$mode = $argv[1] ?? '--probe';
$prepared = [];
foreach ($index as $entry) {
    $path = $root.'/'.$entry['path'];
    if (hash_file('sha256', $path) !== $entry['sha256']) {
        throw new RuntimeException('Source fixture hash mismatch');
    }
    $fixture = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if ($fixture['expected_result']['status'] !== 'LOCAL_ISS_DOCUMENT_PRICES_COMPOSED') {
        continue;
    }
    $row = price112Rebase($fixture, $template, $knowledge, $factory);
    $row['source_fixture_path'] = $entry['path'];
    $row['source_fixture_sha256'] = $entry['sha256'];
    $row['status'] = 'ACTION_REQUIRED';
    $row['reason'] = 'RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN';
    $row['price_status'] = 'LOCAL_CONTRACT_PRICE_PAIR_EVIDENCE_BOUND';
    $row['composition_status'] = 'LOCAL_ISS_DOCUMENT_PRICES_COMPOSED';
    $prepared[] = $row;
    if ($mode === '--probe') {
        break;
    }
}
if ($mode === '--probe') {
    $input = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($prepared[0]['scenario']);
    $result = (new RtcScenarioRunService($factory))->evaluate($knowledge, $input)->toArray();
    $integrated = $factory->makeFromKnowledge($knowledge)->evaluate($input->input)->toArray();
    echo json_encode(['scope' => 'SYNTHETIC_PRICE112_TRANSPORT_PROBE', 'qualification_hash_cycle' => false,
        'expected_iss_prices' => $prepared[0]['expected_iss_prices'], 'rtc' => $result, 'integrated' => $integrated], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    exit(0);
}
if ($mode !== '--install') {
    throw new RuntimeException('Use --probe or --install');
}
if (count($prepared) !== 50) {
    throw new RuntimeException('All50 accepted111 compositions must be represented');
}
$base = $prepared[9];
$add = function (string $name, callable $mutate, string $status, string $reason, ?string $priceStatus, ?string $compositionStatus) use (&$prepared, $base): void {
    $row = $base;
    $row['source_case_name'] = 'transport-'.$name;
    $row['scenario']['scenario_id'] = 'PRICE112-transport-'.$name;
    $mutate($row['scenario']['facts']);
    $row['status'] = $status;
    $row['reason'] = $reason;
    $row['price_status'] = $priceStatus;
    $row['composition_status'] = $compositionStatus;
    $row['negative_mutation'] = $name;
    $prepared[] = $row;
};
$add('missing-price-records', function (&$f) {
    unset($f[DocumentPriceFactProjection112::PRICE_RECORDS]);
}, 'ACTION_REQUIRED',
    'RTC_EXPORT_PRICE112_MATERIAL_RESULT_IDENTITY_AND_PRICE_RECORDS_REQUIRED', null, null);
$add('missing-composition-proof', function (&$f) {
    unset($f[DocumentPriceFactProjection112::COMPOSITION_PROOFS]);
}, 'ACTION_REQUIRED',
    'RTC_EXPORT_PRICE112_MATERIAL_RESULT_IDENTITY_AND_PRICE_RECORDS_REQUIRED', null, null);
$add('price-proof-hash', function (&$f) {
    $f[DocumentPriceFactProjection112::PRICE_PROOFS][0]['source_hash'] = str_repeat('0', 64);
},
    'INVALID', 'PRICE110_PRICE_PROOF_ENVELOPE_HASH_CONFLICT', 'INVALID', null);
$add('composition-proof-hash', function (&$f) {
    $f[DocumentPriceFactProjection112::COMPOSITION_PROOFS][0]['source_hash'] = str_repeat('0', 64);
},
    'INVALID', 'PRICE111_COMPOSITION_ENVELOPE_HASH_CONFLICT', 'LOCAL_CONTRACT_PRICE_PAIR_EVIDENCE_BOUND', 'INVALID');
foreach (['composition-binding', 'composition-observation', 'composition-reuse', 'composition-coverage', 'composition-revoked'] as $kind) {
    $reason = ['composition-binding' => 'PRICE111_CURRENT_COMPOSITION_CONTEXT_CONFLICT',
        'composition-observation' => 'PRICE111_COMPOSITION_OBSERVATION_OUTSIDE_DEPENDENCIES_OR_KNOWLEDGE',
        'composition-reuse' => 'PRICE111_INDEPENDENT_ADJUSTMENT_EVIDENCE_REQUIRED',
        'composition-coverage' => 'PRICE111_COMPOSITION_DOES_NOT_COVER_OWN_ISS_DATES',
        'composition-revoked' => 'PRICE111_COMPOSITION_EVIDENCE_REVOKED_OR_SUPERSEDED'][$kind];
    $status = in_array($kind, ['composition-binding', 'composition-reuse'], true) ? 'INVALID' : 'ACTION_REQUIRED';
    $add($kind, function (&$f) use ($kind) {
        $p = &$f[DocumentPriceFactProjection112::COMPOSITION_PROOFS][0];
        if ($kind === 'composition-binding') {
            $p['value']['price110_binding_hash'] = str_repeat('0', 64);
        }
        if ($kind === 'composition-observation') {
            $p['observed_at'] = '2026-10-19T19:00:01+00:00';
        }
        if ($kind === 'composition-reuse') {
            $p['evidence_id'] = $f[DocumentPriceFactProjection112::PRICE_PROOFS][0]['evidence_id'];
        }
        if ($kind === 'composition-coverage') {
            $p['valid_from'] = '2027-01-01';
        }
        if ($kind === 'composition-revoked') {
            $p['revoked_at'] = '2026-10-19T19:00:00.123456+00:00';
        }
        $p = price112Capture($p, 'synthetic://price112/negative/'.$kind);
        unset($p);
    }, $status, $reason, 'LOCAL_CONTRACT_PRICE_PAIR_EVIDENCE_BOUND', $status);
}
$add('mutated-monetary-principal', function (&$f) {
    $f[DocumentPriceFactProjection112::COMPOSITION_RECORDS][0]['principal'] = '1001.00';
},
    'INVALID', 'PRICE110_FRESH_DATED_IDENTITY_PAIR_REQUIRED', 'INVALID', null);
$add('mutated-contract-price', function (&$f) {
    $f[DocumentPriceFactProjection112::PRICE_RECORDS][0]['service_price'] = '1001.00';
},
    'INVALID', 'PRICE110_FRESH_DATED_IDENTITY_PAIR_REQUIRED', 'INVALID', null);
$add('mixed-dated108-optin', function (&$f) {
    $f['rtc.assess_export_dated_documents'] = true;
}, 'ACTION_REQUIRED',
    'RTC_EXPORT_PRICE112_SEPARATE_LEGACY_CONTEXT_REQUIRED', null, null);
$add('mixed-legacy-optin', function (&$f) {
    $f['rtc.assess_export_iss_results'] = true;
}, 'ACTION_REQUIRED',
    'RTC_EXPORT_PRICE112_SEPARATE_LEGACY_CONTEXT_REQUIRED', null, null);
$add('mixed-temporal-optin', function (&$f) {
    $f['rtc.assess_tax_point_events'] = true;
}, 'ACTION_REQUIRED',
    'RTC_EXPORT_PRICE112_SEPARATE_TEMPORAL_CONTEXT_REQUIRED', null, null);
$add('nonpartial-route', function (&$f) {
    $f['rtc.export_share_percent'] = '100';
}, 'ACTION_REQUIRED',
    'RTC_EXPORT_PRICE112_PARTIAL_EXPORT_ROUTE_REQUIRED', null, null);
$add('disabled-optin', function (&$f) {
    $f['rtc.assess_export_document_prices'] = false;
}, 'ACTION_REQUIRED',
    'RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN', null, null);
$upstream = json_decode((string) file_get_contents($root.'/tests/Fixtures/dated108-upstream-expectations.json'), true, 512, JSON_THROW_ON_ERROR)['cases'];
foreach ($upstream as $old) {
    if ($old['dated_status'] !== null) {
        continue;
    }
    $original = json_decode((string) file_get_contents($root.'/'.$old['path']), true, 512, JSON_THROW_ON_ERROR);
    $row = $base;
    $row['source_case_name'] = 'upstream108-'.$old['name'];
    $row['scenario'] = $original;
    $row['scenario']['scenario_id'] = 'PRICE112-'.$row['source_case_name'];
    $row['scenario']['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $row['scenario']['catalog_versions'] = $knowledge['catalog_versions'];
    $row['scenario']['knowledge_at'] = $base['scenario']['knowledge_at'];
    $row['scenario']['facts']['rtc.assess_export_dated_documents'] = false;
    $row['scenario']['facts']['rtc.assess_export_document_prices'] = true;
    foreach ([DocumentPriceFactProjection112::PRICE_RECORDS, DocumentPriceFactProjection112::PRICE_PROOFS,
        DocumentPriceFactProjection112::COMPOSITION_RECORDS, DocumentPriceFactProjection112::COMPOSITION_PROOFS] as $key) {
        $row['scenario']['facts'][$key] = $base['scenario']['facts'][$key];
    }
    $row['status'] = $old['status'];
    $row['reason'] = match ($old['reason']) {
        'RTC_EXPORT_DATED108_PARTIAL_EXPORT_ROUTE_REQUIRED' => 'RTC_EXPORT_PRICE112_PARTIAL_EXPORT_ROUTE_REQUIRED',
        'RTC_EXPORT_DATED108_SEPARATE_TEMPORAL_CONTEXT_REQUIRED' => 'RTC_EXPORT_PRICE112_SEPARATE_TEMPORAL_CONTEXT_REQUIRED',
        default => $old['reason'],
    };
    $row['price_status'] = null;
    $row['composition_status'] = null;
    $row['upstream108_path'] = $old['path'];
    $row['upstream108_sha256'] = hash_file('sha256', $root.'/'.$old['path']);
    $prepared[] = $row;
}
$directory = $root.'/resources/fiscal/scenarios/document-prices-2026.10.19-112-draft2';
if (is_dir($directory)) {
    throw new RuntimeException('Exclusive112 scenario directory already exists');
}
mkdir($directory);
$manifest = [];
foreach ($prepared as $i => $row) {
    $name = preg_replace('/[^a-z0-9-]/', '-', strtolower($row['source_case_name']));
    $relative = 'resources/fiscal/scenarios/document-prices-2026.10.19-112-draft2/'.sprintf('%03d', $i).'-'.$name.'.json';
    $handle = fopen($root.'/'.$relative, 'x');
    fwrite($handle, json_encode($row['scenario'], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    fclose($handle);
    unset($row['scenario']);
    $manifest[] = ['name' => $name, 'path' => $relative, 'scenario_sha256' => hash_file('sha256', $root.'/'.$relative),
        ...$row];
}
$target = $root.'/tests/Fixtures/price112-scenario-expectations.json';
$handle = fopen($target, 'x');
fwrite($handle, json_encode(['cases' => $manifest, 'bundle' => $bundle,
    'synthetic_fixture_knowledge' => '2026-10-19T19:00:00.123456+00:00', 'upstream108_expansion_pending' => true,
    'production_ready' => false], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
fclose($handle);
echo json_encode(['scenarios' => count($manifest), 'manifest_sha256' => hash_file('sha256', $target), 'production_ready' => false], JSON_THROW_ON_ERROR);
