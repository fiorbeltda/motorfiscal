<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;

require_once __DIR__.'/phase4b_iss_currency140_fixture.php';

/** Synthetic qualified consultancy, with declared legacy tax ledger; no rate or source authentication approval. */
function import141Scenario(array $knowledge): array
{
    $input = issCurrency140Scenario($knowledge);
    $seed = json_decode(file_get_contents(resource_path('fiscal/scenarios/import-fx-2026.10.14-68-draft1-v3/usd-direct.json')), true, 512, JSON_THROW_ON_ERROR);
    $input['scenario_id'] = 'SYNTHETIC-INTEGRATED-IMPORT141';
    $input['knowledge_at'] = (new DateTimeImmutable($knowledge['knowledge_available_at']))->modify('+5 seconds')->format('Y-m-d\TH:i:s.uP');
    $facts = array_replace($seed['facts'], $input['facts']);
    unset($facts['provider.municipality_ibge']);
    $facts = array_replace($facts, ['rtc.evaluate' => true, 'rtc.supplier_id' => 'US-FOREIGN-123', 'rtc.document_id' => 'foreign-invoice-1',
        'rtc.destination_municipality_ibge' => '3550308', 'rtc.performance_municipality_ibge' => '3550308',
        'rtc.service_nbs_code' => '1.1401.11.00', 'rtc.service_nbs_evidence_id' => 'SYNTHETIC-CONSULTANCY-NBS141',
        'iss.document_reference' => 'foreign-invoice-1', 'iss.document_supplier_id' => 'US-FOREIGN-123', 'iss.document_customer_id' => '22222222000191',
        'rtc.acquirer_regime' => 'REGULAR', 'rtc.legacy_tax_inclusion' => 'INCLUDED', 'rtc.base_adjustments' => []]);
    foreach (['ISS' => '259.96', 'PIS' => '85.46', 'COFINS' => '393.64'] as $tax => $amount) {
        $facts['rtc.base_adjustments'][] = ['kind' => 'LEGACY_TAX_EXCLUSION', 'tax' => $tax, 'amount' => $amount,
            'currency' => 'BRL', 'evidence_id' => 'SYNTHETIC-PREQUALIFIED-IMPORT-TAX-LEDGER141-'.$tax];
    }
    $proof = $facts['rtc.import_fx_proof'][0];
    $proof['observed_at'] = $input['knowledge_at'];
    $proof['value']['supplier_id'] = $facts['rtc.supplier_id'];
    $proof['value']['document_id'] = $facts['rtc.document_id'];
    unset($proof['source_hash']);
    $proof['source_hash'] = CanonicalJson::hash($proof);
    $facts['rtc.import_fx_proof'] = [$proof];
    $proof = $facts['iss.document_currency_proof_records'][0];
    $proof['observed_at'] = $input['knowledge_at'];
    $proof['value']['document_reference'] = $facts['iss.document_reference'];
    $proof['value']['supplier_id'] = $facts['iss.document_supplier_id'];
    $proof['value']['customer_id'] = $facts['iss.document_customer_id'];
    $facts['iss.document_currency_proof_records'] = [own135Capture($proof)];
    $input['facts'] = $facts;

    return $input;
}

/** Explicit normalized declaration of the synthetic scenario, not a captured provider invoice. */
function import141Invoice(array $input): array
{
    $facts = $input['facts'];

    return ['contract' => 'IMPORT-DOCUMENT-MONEY-141-V1', 'coverage' => 'SINGLE_SERVICE_LINE_ENTIRE_INVOICE',
        'invoice_id' => $facts['rtc.document_id'], 'line_id' => 'SYNTHETIC-LINE-1',
        'supplier_nif' => $facts['rtc.supplier_id'], 'acquirer_id' => $facts['rtc.acquirer_id'],
        'service_id' => $facts['service.canonical_id'], 'nbs_code' => $facts['rtc.service_nbs_code'],
        'supply_on' => $facts['iss.document_supply_on'], 'supply_event_reference' => $facts['iss.supply_event_reference'],
        'currency' => $facts['iss.original_price_currency'], 'service_price' => $facts['iss.original_price_amount'],
        'legacy_taxes' => $facts['rtc.base_adjustments']];
}
