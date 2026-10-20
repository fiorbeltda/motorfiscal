<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use App\Modules\Fiscal\ConsumptionReform\DatedDocumentFactProjection108;
use App\Modules\Fiscal\ConsumptionReform\PartialExportDatedDpsIdentity107;
use App\Modules\Fiscal\ConsumptionReform\PartialExportIssDatedResults106;
use App\Modules\Fiscal\ConsumptionReform\PartialExportIssMaterialDate105;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

function dated108UpstreamSeal(array $proof): array
{
    unset($proof['source_hash']);
    $proof['source_hash'] = CanonicalJson::hash($proof);

    return $proof;
}

function dated108UpstreamMaterialProof(string $kind, string $id, array $value, string $observed): array
{
    $proof = ['contract' => PartialExportIssMaterialDate105::CONTRACT, 'kind' => $kind, 'evidence_id' => $id,
        'valid_from' => '2026-01-01', 'valid_to' => '2032-12-31', 'observed_at' => $observed,
        'revoked_at' => null, 'superseded_at' => null, 'value' => $value];
    $content = CanonicalJson::encode($proof);
    $proof['origin'] = ['reference' => 'synthetic://dated108/'.$id, 'media_type' => 'application/json',
        'content' => $content, 'content_sha256' => hash('sha256', $content)];

    return dated108UpstreamSeal($proof);
}

$root = dirname(__DIR__);
$seed = json_decode((string) file_get_contents('/tmp/dated108-upstream-seed.json'), true, 512, JSON_THROW_ON_ERROR);
$factory = new FiscalEngineFactory;
$knowledge = $factory->captureKnowledge($root.'/resources/fiscal', $seed['bundle']);
$directory = $root.'/resources/fiscal/scenarios/dated-upstream-2026.10.19-108-draft3';
if (is_dir($directory)) {
    throw new RuntimeException('Exclusive upstream directory already exists');
}
mkdir($directory);
$now = (new DateTimeImmutable)->format('Y-m-d\TH:i:s.uP');
$manifest = [];
foreach ($seed['cases'] as $index => $selection) {
    $scenario = $selection['source'];
    $original = $scenario['facts'];
    $facts = $original;
    foreach (['rtc.assess_export_iss_results', 'rtc.assess_export_sp_results', 'rtc.assess_export_dps_identity'] as $key) {
        $facts[$key] = false;
    }
    $facts['rtc.assess_export_dated_documents'] = true;
    $facts['provider.iss_regime'] = 'GENERAL_AD_VALOREM';
    $facts['provider.iss_regime_proof_records'] = [dated108UpstreamMaterialProof('ISS_SUPPLIER_REGIME_MATERIAL_EVIDENCE',
        'REGIME-'.$selection['name'], ['supplier_id' => $facts['rtc.supplier_id'], 'supplier_municipality' => $facts['provider.municipality_ibge'],
            'service_kind' => 'SOFTWARE_SERVICE', 'regime' => 'GENERAL_AD_VALOREM', 'special_regime' => false,
            'supplier_immunity' => false, 'supplier_flat_tax' => false, 'simples_option' => false], $now)];
    $material = [];
    $results = [];
    if (! $selection['fx_refusal_before_nominal']) {
        $contexts = array_column($original['rtc.export_iss_result_contexts'], 'iss_context', 'role');
        $municipalRecords = $original['rtc.export_sp_result_proofs'] ?? [];
        $municipalForRole = [];
        foreach ($municipalRecords as $record) {
            if (! isset($municipalForRole[$record['role']])) {
                $municipalForRole[$record['role']] = $record['municipal_proof'];
            }
        }
        foreach (['RTC_FOREIGN', 'RTC_DOMESTIC'] as $role) {
            $ref = $facts[$role === 'RTC_FOREIGN' ? 'rtc.export_foreign_document_id' : 'rtc.export_domestic_document_id'];
            $recipient = $facts[$role === 'RTC_FOREIGN' ? 'rtc.recipient_id' : 'rtc.export_domestic_recipient_id'];
            $mv = $municipalForRole[$role]['value'] ?? [];
            $monthly = ($mv['service_execution'] ?? null) === 'CONTINUING_MONTHLY';
            $day = $facts['rtc.operation_date'];
            $performance = ['contract_reference' => $facts['rtc.export_transaction_evidence_id'],
                'unit_reference' => $monthly ? $mv['monthly_stage']['stage_id'] : 'SYNTHETIC-UNIT-'.$selection['name'],
                'mode' => $monthly ? 'FULFILLED_MONTHLY_STAGE' : 'ONE_OFF_COMPLETED_SERVICE',
                'performed_from' => $monthly ? substr($day, 0, 7).'-01' : $day, 'performed_to' => $day,
                'performed_at' => $day.'T18:00:00.123456-03:00', 'iss_supply_on' => $day,
                'unit_completed' => true, 'final_delivery' => ! $monthly, 'document_state' => 'ORIGINAL_ACTIVE'];
            $value = ['operation_reference' => $facts['rtc.export_operation_reference'], 'document_reference' => $ref,
                'role' => $role, 'supplier_id' => $facts['rtc.supplier_id'], 'acquirer_id' => $facts['rtc.acquirer_id'],
                'recipient_id' => $recipient, 'supplier_municipality' => $facts['provider.municipality_ibge'],
                'transaction_evidence_id' => $facts['rtc.export_transaction_evidence_id'], 'rtc_operation_date' => $day,
                'service_kind' => 'SOFTWARE_SERVICE', 'event_kind' => $monthly ? 'MONTHLY_STAGE_FULFILLED' : 'ACTUAL_SERVICE_COMPLETED',
                'performance' => $performance];
            $material[] = ['role' => $role, 'document_reference' => $ref, 'performance' => $performance,
                'proof' => dated108UpstreamMaterialProof('ISS_SERVICE_PERFORMANCE_MATERIAL_EVIDENCE', 'MATERIAL-'.$selection['name'].'-'.$role, $value, $now)];
        }
        $materialByRole = array_column($material, null, 'role');
        // Iterate original municipal records without deduplication: malformed/missing/duplicate inputs stay negative.
        foreach ($municipalRecords as $mr) {
            $role = $mr['role'];
            $context = $contexts[$role];
            $m = $materialByRole[$role];
            $p = $m['performance'];
            $value = ['operation_reference' => $facts['rtc.export_operation_reference'], 'role' => $role,
                'document_reference' => $m['document_reference'], 'supplier_id' => $facts['rtc.supplier_id'],
                'recipient_reference' => $context['recipient_id'], 'transaction_evidence_id' => $facts['rtc.export_transaction_evidence_id'],
                'rtc_operation_date' => $facts['rtc.operation_date'], 'iss_supply_on' => $p['iss_supply_on'],
                'contract_reference' => $p['contract_reference'], 'unit_reference' => $p['unit_reference'],
                'performed_at' => $p['performed_at'], 'performance_hash' => CanonicalJson::hash($p),
                'material_proof_hash' => $m['proof']['source_hash'], 'material_origin_hash' => $m['proof']['origin']['content_sha256'],
                'national_context_hash' => CanonicalJson::hash($context), 'national_result_proof_hash' => $context['result_proof']['source_hash'],
                'municipal_proof_hash' => $mr['municipal_proof']['source_hash']];
            $binding = ['contract' => PartialExportIssDatedResults106::CONTRACT, 'kind' => 'MATERIAL_RESULT_SAME_EVENT_BINDING',
                'evidence_id' => 'EVENT-'.$selection['name'].'-'.$role, 'value' => $value];
            $binding['origin_content'] = CanonicalJson::encode($binding);
            $binding['origin_sha256'] = hash('sha256', $binding['origin_content']);
            $binding = dated108UpstreamSeal($binding);
            $results[] = ['role' => $role, 'iss_context' => $context, 'municipal_proof' => $mr['municipal_proof'], 'event_binding' => $binding];
        }
    }
    $facts['rtc.export_iss_material_records'] = $material;
    $facts['rtc.export_iss_dated_result_records'] = $results;
    $scenario['scenario_id'] = 'DATED108-'.$selection['name'];
    $scenario['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $scenario['catalog_versions'] = $knowledge['catalog_versions'];
    $scenario['knowledge_at'] = max($now, $selection['source']['knowledge_at']);
    $scenario['facts'] = $facts;
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($scenario);
    $projection = DatedDocumentFactProjection108::from($loaded->input->context->facts)['facts'];
    $policy = $knowledge['documents']['rtc_code_catalog']['partial_export_dated_documents_policy'];
    $dated = (new PartialExportIssDatedResults106)->qualify($projection, $material, $results, $scenario['knowledge_at'], $policy['dated_results_policy']);
    $datedByRole = array_column($dated['documents'] ?? [], null, 'role');
    $identities = [];
    if (! $selection['fx_refusal_before_nominal']) {
        foreach ($original['rtc.export_dps_identity_records'] as $record) {
            $role = $record['role'];
            $identity = $record['identity'];
            $proof = ['contract' => PartialExportDatedDpsIdentity107::CONTRACT, 'operation_reference' => $facts['rtc.export_operation_reference'],
                'document_reference' => $record['document_reference'], 'role' => $role,
                'transaction_evidence_id' => $facts['rtc.export_transaction_evidence_id'], 'supplier_id' => $identity['supplier_id'],
                'issuer_municipality' => $identity['issuer_municipality'], 'identity_hash' => CanonicalJson::hash($identity),
                'dated_pair_binding_hash' => $dated['binding_hash'] ?? '',
                'dated_document_hash' => isset($datedByRole[$role]) ? CanonicalJson::hash($datedByRole[$role]) : '',
                'observed_at' => $scenario['knowledge_at'], 'evidence_id' => 'MAPPING-'.$selection['name'].'-'.$role];
            $identities[] = ['role' => $role, 'document_reference' => $record['document_reference'], 'identity' => $identity,
                'proof' => dated108UpstreamSeal($proof)];
        }
    }
    $scenario['facts']['rtc.export_dated_dps_identity_records'] = $identities;
    $relative = 'resources/fiscal/scenarios/dated-upstream-2026.10.19-108-draft3/'.$selection['name'].'.json';
    $h = fopen($root.'/'.$relative, 'x');
    fwrite($h, json_encode($scenario, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fclose($h);
    $manifest[] = ['name' => $selection['name'], 'path' => $relative, 'scenario_sha256' => hash_file('sha256', $root.'/'.$relative),
        'original_path' => $selection['original_path'], 'original_sha256' => $selection['original_sha256'],
        'original_knowledge_at' => $selection['source']['knowledge_at'], 'status' => $selection['status'], 'reason' => $selection['reason'],
        'dated_status' => $selection['dated_status'], 'dated_reason' => $selection['dated_reason'], 'dated_subreason' => $selection['dated_subreason'],
        'fx_refusal_before_nominal' => $selection['fx_refusal_before_nominal'], 'note' => $selection['note']];
}
$h = fopen($root.'/tests/Fixtures/dated108-upstream-expectations.json', 'x');
fwrite($h, json_encode(['bundle' => $seed['bundle'], 'cases' => $manifest, 'production_ready' => false], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
fclose($h);
echo json_encode(['upstream_scenarios' => count($manifest), 'source95_97_proof_bodies_preserved' => true, 'production_ready' => false], JSON_THROW_ON_ERROR);
