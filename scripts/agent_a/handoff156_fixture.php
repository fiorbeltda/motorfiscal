<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\CreditEvidence156;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

require_once __DIR__.'/handoff155_fixture.php';

/** @return array<string, mixed> */
function evidence156(array $record): array
{
    $record += ['source_kind' => 'SYNTHETIC_TEST', 'evidence_id' => 'SYNTHETIC.EVIDENCE156',
        'source_hash' => hash('sha256', 'SYNTHETIC.SOURCE156'), 'observed_at' => '2026-11-04T10:00:00Z'];

    return ['record' => $record, 'record_hash' => CanonicalJson::hash($record)];
}

/** @return array<string, mixed> */
function book156(array $facts): array
{
    return evidence156(['book_id' => 'BOOK156', 'entry_id' => 'ENTRY156', 'document_id' => $facts['rtc.input_document_id'],
        'taxpayer_id' => $facts['rtc.acquirer_id'], 'document_on' => '2026-09-25', 'booked_on' => '2026-10-01',
        'written' => true, 'ibs_amount' => $facts['rtc.input_ibs_amount'], 'cbs_amount' => $facts['rtc.input_cbs_amount']]);
}

/** @return array<string, mixed> */
function import156(): array
{
    $input = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/import-fx-credit-2026.10.14-68-draft1/proven.json')), true, 512, JSON_THROW_ON_ERROR);
    $input['knowledge_at'] = '2026-11-05T10:00:00Z';
    $facts = &$input['facts'];
    $facts['rtc.input_document_valid'] = true;
    $facts['rtc.prior_tax_extinguished'] = true;
    $facts['rtc.credit_book_entries'] = [book156($facts)];
    $facts['rtc.import_definitive_credit_records'] = [evidence156(['contract' => 'IMPORT-CREDIT-A156-V1',
        'document_id' => $facts['rtc.input_document_id'], 'foreign_invoice_id' => $facts['rtc.import_document_id'],
        'acquirer_id' => $facts['rtc.acquirer_id'], 'importer_id' => $facts['rtc.importer_id'],
        'document_on' => '2026-09-25', 'credit_on' => '2026-10-01', 'definitive' => true, 'document_valid' => true,
        'extinction_evidence_id' => $facts['rtc.import_credit_extinction_evidence_id'],
        'payments' => array_map(fn (string $tax): array => ['tax' => $tax, 'document_id' => $facts['rtc.input_document_id'],
            'payer_id' => $facts['rtc.importer_id'], 'extinguished' => true, 'payment_id' => 'PAY156.'.$tax,
            'evidence_id' => 'PAYMENT.PROOF156.'.$tax, 'paid_on' => '2026-09-26', 'amount' => $facts['rtc.input_'.strtolower($tax).'_amount']], ['IBS', 'CBS'])])];

    return $input;
}

/** New facts and gate only in captured memory; never installs a bundle. @return array<string, mixed> */
function knowledge156(string $bundle = 'bundles/consumption-phase4b-2026.10.14-68-draft1.json', bool $enabled = true): array
{
    static $bases = [];
    $bases[$bundle] ??= (new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'), $bundle);
    $knowledge = $bases[$bundle];
    if ($enabled) {
        $knowledge['documents']['rtc_code_catalog'][CreditEvidence156::KEY] = CreditEvidence156::POLICY;
    }
    foreach (['rtc.credit_book_entries', 'rtc.import_definitive_credit_records', 'rtc.legal_base_reduction_records'] as $key) {
        $knowledge['documents']['fact_definitions'][] = ['key' => $key, 'type' => 'ARRAY', 'material' => true];
    }
    $candidate = 'A156.IN_MEMORY.TEST.'.substr(hash('sha256', $bundle), 0, 12).($enabled ? '.DOCUMENTARY' : '.ARITHMETIC');
    $knowledge['rule_bundle_id'] = $candidate;
    $knowledge['bundle_version'] = $candidate;
    $knowledge['documents']['bundle_manifest']['bundle_id'] = $candidate;
    $knowledge['documents']['bundle_manifest']['version'] = $candidate;
    foreach (['rtc_codes' => 'rtc_code_catalog', 'rtc_rates' => 'rtc_rate_catalog'] as $name => $document) {
        $knowledge['catalog_versions'][$name] = $candidate;
        $knowledge['documents']['bundle_manifest']['catalog_versions'][$name] = $candidate;
        $knowledge['documents'][$document]['version'] = $candidate;
    }

    return refresh156($knowledge);
}

/** @return array<string, mixed> */
function refresh156(array $knowledge): array
{
    foreach ($knowledge['documents']['bundle_manifest']['content_hashes'] as $key => $_) {
        $knowledge['documents']['bundle_manifest']['content_hashes'][$key] = CanonicalJson::hash($knowledge['documents'][$key]);
    }
    foreach ($knowledge['documents'] as $key => $document) {
        if (array_key_exists($key, $knowledge['content_hashes'])) {
            $knowledge['content_hashes'][$key] = CanonicalJson::hash($document);
        }
    }

    return $knowledge;
}

/** @return array<string, mixed> */
function evaluate156(array $input, array $knowledge, bool $integrated = false): array
{
    $factory = new FiscalEngineFactory;
    $knowledge = refresh156($knowledge);
    $input['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $input['catalog_versions'] = $knowledge['catalog_versions'];
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input);
    if ($integrated) {
        return $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input)->toArray();
    }
    $documents = $knowledge['documents'];

    return (new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog'])))
        ->evaluate($loaded->input->context->facts, $loaded->input->context->competenceDate, $loaded->input->context->knowledgeAt);
}
