<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DocumentPriceFactProjection112;
use App\Modules\Fiscal\ConsumptionReform\PartialExportIssDatedResults106;
use App\Modules\Fiscal\ConsumptionReform\PartialExportIssPriceComposition111;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\ISS\IssDocumentBase133;

require_once __DIR__.'/build_phase4b_price112_scenarios.php';

/** Reissue only explicit synthetic mapping claims; preserve historical fixtures and business evidence.
 * @return array<string, mixed>
 */
function phase4bIss133Fixture(string $seed = '000-no-adjustments-gross-before-all-discounts', ?string $amount = null, bool $reverseResults = false, string $knowledgeAt = '2026-10-22T18:20:00.000000+00:00', ?array $declaredPrices = null, ?array $identityOverrides = null): array
{
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.22-131-draft2.json');
    $fixture = json_decode((string) file_get_contents(base_path('tests/Fixtures/price111-native112/'.$seed.'.json')), true, 512, JSON_THROW_ON_ERROR);
    $fixture['input']['known_at'] = $knowledgeAt;
    if ($identityOverrides !== null) {
        foreach ($fixture['input']['input110']['identity_records'] as &$identityRecord) {
            $override = $identityOverrides[$identityRecord['role']] ?? throw new RuntimeException('Explicit identity required for both synthetic documents.');
            foreach ($override as $key => $value) {
                if (! in_array($key, ['issued_at', 'number', 'series'], true)) {
                    throw new RuntimeException('Only explicit emission identity fields may be overridden.');
                }
                $identityRecord['identity'][$key] = $value;
            }
            $identity = &$identityRecord['identity'];
            $identity['id'] = 'DPS'.$identity['issuer_municipality'].'2'.$identity['supplier_id']
                .str_pad($identity['series'], 5, '0', STR_PAD_LEFT).str_pad($identity['number'], 15, '0', STR_PAD_LEFT);
            $identityRecord['proof']['identity_hash'] = CanonicalJson::hash($identity);
            $identityRecord['proof'] = price112Seal($identityRecord['proof']);
            unset($identity);
        }
        unset($identityRecord);
    }
    if ($reverseResults) {
        foreach ($fixture['input']['input110']['result_records'] as &$record) {
            $scope = $record['role'] === 'RTC_FOREIGN' ? 'BR' : 'ABROAD';
            $record['iss_context']['result_proof']['value']['result_scope'] = $scope;
            $record['iss_context']['result_proof'] = price112Seal($record['iss_context']['result_proof']);
            $record['municipal_proof']['value']['national_result_proof_hash'] = $record['iss_context']['result_proof']['source_hash'];
            $record['municipal_proof']['value']['result_subject_scope'] = $scope;
            $record['municipal_proof']['value']['br_person_link'] = $scope === 'BR' ? 'HAS_BR_LINK' : 'NO_BR_LINK';
            $record['municipal_proof'] = price112Seal($record['municipal_proof']);
            $record['event_binding']['value']['national_context_hash'] = CanonicalJson::hash($record['iss_context']);
            $record['event_binding']['value']['national_result_proof_hash'] = $record['iss_context']['result_proof']['source_hash'];
            $record['event_binding']['value']['municipal_proof_hash'] = $record['municipal_proof']['source_hash'];
            $record['event_binding']['origin_content'] = CanonicalJson::encode(array_intersect_key($record['event_binding'],
                array_flip(['contract', 'kind', 'evidence_id', 'value'])));
            $record['event_binding']['origin_sha256'] = hash('sha256', $record['event_binding']['origin_content']);
            $record['event_binding'] = price112Seal($record['event_binding']);
        }
        unset($record);
    }
    if ($amount !== null && $declaredPrices !== null) {
        throw new RuntimeException('Use one explicit synthetic price declaration.');
    }
    if ($amount !== null || $declaredPrices !== null) {
        if ($seed !== '000-no-adjustments-gross-before-all-discounts') {
            throw new RuntimeException('Explicit no-adjustment seed required for new synthetic prices.');
        }
        $prices = $declaredPrices ?? ['RTC_FOREIGN' => $amount, 'RTC_DOMESTIC' => $amount];
        if (count($prices) !== 2 || ! isset($prices['RTC_FOREIGN'], $prices['RTC_DOMESTIC'])) {
            throw new RuntimeException('Two explicit synthetic documentary prices required.');
        }
        foreach ($fixture['input']['records'] as &$record) {
            $record['principal'] = $prices[$record['role']];
        }
        unset($record);
        foreach ($fixture['input']['input110']['price_records'] as &$record) {
            $record['service_price'] = $prices[$record['role']];
        }
        unset($record);
        foreach ($fixture['expected_result']['documents'] as &$document) {
            foreach (['service_price', 'gross_service_price', 'price_for_iss_evaluation_in_input_currency',
                'payment_candidate_if_conditional_terms_satisfied'] as $key) {
                $document[$key] = $prices[$document['role']];
            }
        }
        unset($document);
        $fixture['input']['input110']['proof']['value']['contract_service_price_total'] = DecimalValue::fromString($prices['RTC_FOREIGN'])
            ->plus(DecimalValue::fromString($prices['RTC_DOMESTIC']))->value();
    }
    $template = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/dps-identity-2026.10.19-104-draft1/local-pair.json')), true, 512, JSON_THROW_ON_ERROR);
    $prepared = price112Rebase($fixture, $template, $knowledge, $factory);
    $scenario = $prepared['scenario'];
    $facts = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($scenario)->input->context->facts;
    $context = DocumentPriceFactProjection112::from($facts)['facts'];
    $input110 = ['facts' => $context, 'material_records' => $facts->value('rtc.export_iss_material_records'),
        'result_records' => $facts->value('rtc.export_iss_dated_result_records'),
        'identity_records' => $facts->value('rtc.export_dated_dps_identity_records'),
        'price_records' => $facts->value(DocumentPriceFactProjection112::PRICE_RECORDS),
        'proof' => $facts->value(DocumentPriceFactProjection112::PRICE_PROOFS)[0]];
    $input111 = ['input110' => $input110, 'records' => $facts->value(DocumentPriceFactProjection112::COMPOSITION_RECORDS),
        'proof' => $facts->value(DocumentPriceFactProjection112::COMPOSITION_PROOFS)[0]];
    $known = $scenario['knowledge_at'];
    $policy = IssDocumentBase133::policy();
    $prices = (new PartialExportIssPriceComposition111)->qualify($input110, $input111['records'], $input111['proof'], $known, $policy['composition_policy']);
    $dated = (new PartialExportIssDatedResults106)->qualify($context, $input110['material_records'], $input110['result_records'],
        $known, $policy['composition_policy']['price_policy']['dated_identity_policy']['dated_results_policy']);
    if (($prices['document_prices_locally_composed'] ?? false) !== true) {
        throw new RuntimeException('Fresh synthetic133 predecessor refused.');
    }
    $resultByRole = array_column($input110['result_records'], null, 'role');
    $proofs = [];
    foreach ($prices['documents'] as $price) {
        $role = $price['role'];
        $value = ['role' => $role, 'document_reference' => $price['document_reference'],
            'contract_reference' => $price['contract_reference'], 'unit_reference' => $price['unit_reference'],
            'line_reference' => $price['line_reference'], 'iss_supply_on' => $price['iss_supply_on'],
            'supplier_id' => $context['rtc.supplier_id'], 'acquirer_id' => $resultByRole[$role]['iss_context']['acquirer_id'],
            'municipality' => '3550308', 'service_id' => 'SOFTWARE_DEVELOPMENT', 'lc116_subitem' => '01.01',
            'ctribnac' => '010101', 'municipal_code' => '02660', 'supplier_person_type' => 'PRIVATE_LEGAL_ENTITY',
            'supplier_iss_regime' => 'GENERAL_AD_VALOREM', 'special_base_rule' => false,
            'iss_included_in_price' => true, 'ordinary_taxation_without_benefit' => true,
            'price111_binding_hash' => $prices['binding_hash'], 'dated106_binding_hash' => $dated['binding_hash'],
            'source_policy_hash' => CanonicalJson::hash($policy)];
        $proof = ['contract' => IssDocumentBase133::CONTRACT, 'kind' => 'ISS_DOCUMENT_TAX_CONTEXT',
            'evidence_id' => 'SYNTHETIC-ISS133-'.$role, 'observed_at' => $known,
            'valid_from' => '2026-01-01', 'valid_to' => '2026-10-22', 'revoked_at' => null,
            'superseded_at' => null, 'value' => $value];
        $proofs[] = price112Capture($proof, 'synthetic://iss133/'.$role);
    }

    return ['input111' => $input111, 'proofs' => $proofs, 'knowledge_at' => $known, 'policy' => $policy,
        'scenario' => $scenario];
}
