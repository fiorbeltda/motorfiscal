<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\AcquirerCollection157;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

require_once __DIR__.'/collection148_fixture.php';
require_once __DIR__.'/handoff156_fixture.php';

/** @return array<string, mixed> */
function seal157(array $input): array
{
    unset($input['input_hash']);
    $input['input_hash'] = CanonicalJson::hash($input);

    return $input;
}

/** @return array<string, mixed> */
function collection157Ledger(): array
{
    $bind = ['document_id' => 'DOC157', 'supplier_id' => 'SUPPLIER157', 'acquirer_id' => 'ACQUIRER157', 'payment_transaction_id' => 'PURCHASE157', 'currency' => 'BRL'];
    $payments = [];
    foreach ([['P1', 'IBS', '40.00', '2026-10-02T10:00:00Z'], ['P2', 'IBS', '70.00', '2026-10-03T10:00:00Z'],
        ['P3', 'CBS', '90.00', '2026-10-03T10:00:00Z']] as [$id, $tax, $amount, $at]) {
        $payments[] = [...$bind, 'id' => $id, 'tax' => $tax, 'amount' => $amount, 'settled_at' => $at, 'settled' => true,
            'payer_id' => 'ACQUIRER157', 'authority_receipt_id' => 'SYNTHETIC.AUTHORITY.'.$id, 'evidence_id' => 'SYNTHETIC.PROOF.'.$id];
    }

    return seal157([...$bind, 'contract' => AcquirerCollection157::CONTRACT, 'source_kind' => 'SYNTHETIC_TEST',
        'known_at' => '2026-11-05T12:00:00Z', 'operation_date' => '2026-10-01', 'operation_at' => '2026-10-01T10:00:00-03:00',
        'history_as_of' => '2026-10-01T18:00:00Z', 'acquirer_regime' => 'REGULAR',
        'instrument_split_capable' => false, 'instrument_evidence_id' => 'SYNTHETIC.INSTRUMENT157', 'evidence_id' => 'SYNTHETIC.LEDGER157',
        'source_hash' => hash('sha256', 'SYNTHETIC.LEDGER157'), 'history_complete' => true, 'payments_complete' => true,
        'other_extinctions_complete' => true, 'history_excludes_acquirer_collections' => true, 'other_extinctions' => [],
        'transfers_complete' => true, 'history_evidence_id' => 'SYNTHETIC.HISTORY157', 'prior_receipt_ids' => ['PRIOR.IBS', 'PRIOR.CBS'],
        'debits' => ['IBS' => '100.00', 'CBS' => '90.00'], 'previously_extinguished' => ['IBS' => '20.00', 'CBS' => '10.00'],
        'payments' => $payments, 'transfers' => [[...$bind, 'id' => 'TRANSFER1', 'tax' => 'IBS', 'amount' => '20.00',
            'collection_payment_id' => 'P2', 'beneficiary_id' => 'SUPPLIER157', 'settled' => true,
            'transferred_at' => '2026-10-04T10:00:00Z', 'authority_transfer_receipt_id' => 'SYNTHETIC.TRANSFER1', 'evidence_id' => 'SYNTHETIC.TRANSFER.PROOF']]]);
}

/** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
function collection157Scenario(bool $enabled = true): array
{
    $knowledge = knowledge156('bundles/consumption-phase4b-2026.09.28-3.json', false);
    $knowledge['documents']['fact_definitions'][] = ['key' => 'rtc.acquirer_collection_ledger', 'type' => 'ARRAY', 'material' => true];
    $candidate = 'A157.IN_MEMORY.TEST.'.($enabled ? 'ON' : 'OFF');
    $knowledge['rule_bundle_id'] = $knowledge['bundle_version'] = $candidate;
    $knowledge['documents']['bundle_manifest']['bundle_id'] = $knowledge['documents']['bundle_manifest']['version'] = $candidate;
    foreach (['rtc_codes' => 'rtc_code_catalog', 'rtc_rates' => 'rtc_rate_catalog'] as $name => $document) {
        $knowledge['catalog_versions'][$name] = $candidate;
        $knowledge['documents']['bundle_manifest']['catalog_versions'][$name] = $candidate;
        $knowledge['documents'][$document]['version'] = $candidate;
    }
    if ($enabled) {
        $knowledge['documents']['rtc_code_catalog'][AcquirerCollection157::KEY] = AcquirerCollection157::POLICY;
    }
    $scenario = collection148Scenario();
    $ledger = collection157Ledger();
    $scenario['knowledge_at'] = $ledger['known_at'];
    foreach (['document_id', 'supplier_id', 'acquirer_id', 'payment_transaction_id', 'acquirer_regime'] as $key) {
        $ledger[$key] = $scenario['facts']['rtc.'.$key];
    }
    $ledger['operation_date'] = $scenario['facts']['rtc.operation_date'];
    $ledger['operation_at'] = $ledger['operation_date'].'T10:00:00-03:00';
    $ledger['instrument_evidence_id'] = $scenario['facts']['rtc.payment_instrument_evidence_id'];
    $ledger['debits'] = ['IBS' => '1.00', 'CBS' => '9.00'];
    $ledger['previously_extinguished'] = ['IBS' => '0.00', 'CBS' => '0.00'];
    $ledger['prior_receipt_ids'] = [];
    $ledger['payments'][0]['amount'] = '0.40';
    $ledger['payments'][1]['amount'] = '0.90';
    $ledger['payments'][2]['amount'] = '8.00';
    $ledger['transfers'][0]['amount'] = '0.10';
    foreach (['payments', 'transfers'] as $collection) {
        foreach ($ledger[$collection] as &$record) {
            foreach (['document_id', 'supplier_id', 'acquirer_id', 'payment_transaction_id'] as $key) {
                $record[$key] = $ledger[$key];
            }
            if ($collection === 'payments') {
                $record['payer_id'] = $ledger['acquirer_id'];
            } else {
                $record['beneficiary_id'] = $ledger['supplier_id'];
            }
        }
        unset($record);
    }
    $scenario['facts']['rtc.acquirer_collection_ledger'] = [seal157($ledger)];

    return [$scenario, refresh156($knowledge)];
}
