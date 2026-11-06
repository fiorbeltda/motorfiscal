<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SplitLedger158;
use App\Modules\Fiscal\ConsumptionReform\SplitPaymentIntegrationB34;

require_once dirname(__DIR__).'/agent_a/collection148_fixture.php';
require_once dirname(__DIR__).'/agent_a/handoff156_fixture.php';
require_once dirname(__DIR__).'/agent_a/split158_fixture.php';

/**
 * B34: one-installment split-payment ledger bound to the regular SaaS scenario (debits IBS 1.00 / CBS 9.00).
 * Every amount is a synthetic input for arithmetic and binding; nothing here is an official rate,
 * deployment, platform response or authority receipt.
 *
 * @param  array<string, mixed>  $facts
 * @return array<string, mixed>
 */
function splitB34Ledger(array $facts, string $knownAt): array
{
    $tx = split158Transaction();
    $ids = ['operation_id' => 'SYNTHETIC.B34.OP', 'document_id' => $facts['rtc.document_id'],
        'supplier_id' => $facts['rtc.supplier_id'], 'acquirer_id' => $facts['rtc.acquirer_id']];
    $txId = $facts['rtc.payment_transaction_id'];
    $day = $facts['rtc.operation_date'];
    $tx = array_replace($tx, $ids, ['transaction_id' => $txId, 'original_payer_id' => $ids['acquirer_id'],
        'original_amount' => '1000.00', 'taxes' => ['IBS' => '1.00', 'CBS' => '9.00'], 'known_at' => $knownAt,
        'operation_at' => $day.'T12:00:00Z', 'document_issued_at' => $day.'T12:00:00Z', 'initiated_at' => $day.'T13:00:00Z',
        'platform_returns' => []]);
    $tx['document_link'] = array_replace($tx['document_link'], ['document_id' => $ids['document_id'], 'transaction_id' => $txId,
        'linked_at' => $day.'T12:30:00Z']);
    $tx['installment'] = array_replace($tx['installment'], ['number' => 1, 'count' => 1, 'operation_id' => $ids['operation_id'],
        'document_id' => $ids['document_id'], 'original_amount' => '1000.00', 'taxes' => ['IBS' => '1.00', 'CBS' => '9.00']]);
    $tx['receivable_advance'] = array_replace($tx['receivable_advance'], ['transaction_id' => $txId,
        'at' => $day.'T14:00:00Z', 'amount' => '500.00']);
    $next = (new DateTimeImmutable($day))->modify('+1 day')->format('Y-m-d');
    $tx['settlement'] = array_replace($tx['settlement'], ['transaction_id' => $txId, 'milestone_at' => $next.'T12:03:00Z',
        'noncontractual_clear_at' => $next.'T12:05:00Z', 'calculation_frozen_at' => $next.'T12:02:00Z',
        'settled_at' => $next.'T12:05:00Z', 'paid_amount' => '1000.00', 'receipt_id' => 'SYNTHETIC.B34.SETTLED']);
    $later = (new DateTimeImmutable($day))->modify('+3 days')->format('Y-m-d');
    $psp = $tx['receiver_psp_id'];
    $ledger = ['contract' => SplitLedger158::CONTRACT, 'currency' => 'BRL', 'source_kind' => 'SYNTHETIC_TEST',
        'source_hash' => hash('sha256', 'B34 synthetic split ledger'), 'evidence_id' => 'SYNTHETIC.B34.LEDGER',
        'known_at' => $knownAt, 'history_as_of' => $day.'T11:00:00Z', ...$ids,
        'operation_amount' => '1000.00', 'debits' => ['IBS' => '1.00', 'CBS' => '9.00'],
        'previously_extinguished' => ['IBS' => '0.00', 'CBS' => '0.00'], 'prior_receipt_ids' => [],
        'schedule_complete' => true, 'reports_complete' => true, 'remittances_complete' => true,
        'applications_complete' => true, 'excess_complete' => true, 'transfers_complete' => true,
        'transactions' => [seal158($tx)], 'excess_determinations' => [], 'transfers' => [],
        'reports' => [['id' => 'B34.R1', 'receiver_psp_id' => $psp, 'arrangement' => 'BOLETO', 'reference_date' => $next,
            'sent_at' => $next.'T21:00:00Z', 'authority_acknowledgement_id' => 'SYNTHETIC.B34.ACK', 'transaction_ids' => [$txId],
            'operation_taxes' => ['IBS' => '1.00', 'CBS' => '9.00'], 'other_operation_taxes' => ['IBS' => '0.00', 'CBS' => '0.00'],
            'batch_taxes' => ['IBS' => '1.00', 'CBS' => '9.00'], 'source_hash' => hash('sha256', 'B34 report'),
            'evidence_id' => 'SYNTHETIC.B34.REPORT']],
        'remittances' => [], 'applications' => []];
    foreach ([['IBS', '1.00', 'STR'], ['CBS', '9.00', 'TES']] as [$tax, $amount, $rail]) {
        $ledger['remittances'][] = ['id' => 'B34.REM.'.$tax, 'tax' => $tax, 'receiver_psp_id' => $psp, 'arrangement' => 'BOLETO',
            'reference_date' => $next, 'report_ids' => ['B34.R1'], 'amount' => $amount, 'rail' => $rail,
            'received_at' => $later.'T12:00:00Z', 'authority_receipt_id' => 'SYNTHETIC.B34.REMIT.'.$tax,
            'source_hash' => hash('sha256', 'B34 remittance '.$tax), 'evidence_id' => 'SYNTHETIC.B34.REMIT.PROOF.'.$tax];
        $ledger['applications'][] = $ids + ['id' => 'B34.APP.'.$tax, 'tax' => $tax, 'modality' => 'SPLIT_PAYMENT', 'applied' => true,
            'remittance_id' => 'B34.REM.'.$tax, 'amount' => $amount, 'applied_at' => $later.'T13:00:00Z',
            'application_receipt_id' => 'SYNTHETIC.B34.APP.'.$tax, 'source_hash' => hash('sha256', 'B34 application '.$tax),
            'evidence_id' => 'SYNTHETIC.B34.APP.PROOF.'.$tax];
    }

    return seal158($ledger);
}

/**
 * In-memory candidate on the A157 base bundle: split route with a SYNTHETIC deployment row (never an official one).
 *
 * @return array{0: array<string, mixed>, 1: array<string, mixed>}
 */
function splitB34Scenario(bool $enabled = true, bool $deployment = true): array
{
    $knowledge = knowledge156('bundles/consumption-phase4b-2026.09.28-3.json', false);
    $knowledge['documents']['fact_definitions'][] = ['key' => 'rtc.split_payment_ledger', 'type' => 'ARRAY', 'material' => true];
    $candidate = 'B34.IN_MEMORY.TEST.'.($enabled ? 'ON' : 'OFF');
    $knowledge['rule_bundle_id'] = $knowledge['bundle_version'] = $candidate;
    $knowledge['documents']['bundle_manifest']['bundle_id'] = $knowledge['documents']['bundle_manifest']['version'] = $candidate;
    foreach (['rtc_codes' => 'rtc_code_catalog', 'rtc_rates' => 'rtc_rate_catalog', 'rtc_transition' => 'rtc_transition_catalog'] as $name => $document) {
        $knowledge['catalog_versions'][$name] = $candidate;
        $knowledge['documents']['bundle_manifest']['catalog_versions'][$name] = $candidate;
        $knowledge['documents'][$document]['version'] = $candidate;
    }
    if ($enabled) {
        $knowledge['documents']['rtc_code_catalog'][SplitPaymentIntegrationB34::KEY] = SplitPaymentIntegrationB34::POLICY;
    }
    $knowledge['documents']['rtc_transition_catalog']['split_deployments'] = $deployment ? [splitB34SyntheticDeployment()] : [];
    $scenario = collection148Scenario(['rtc.collection_method' => 'SPLIT_PAYMENT', 'rtc.collection_legal_basis' => 'LC214 arts. 31-35',
        'rtc.payment_instrument_split_capable' => true, 'rtc.payment_transaction_id' => 'PAY.B34.1',
        'rtc.split_deployment_evidence_id' => 'SYNTHETIC.B34.DEPLOYMENT']);
    $scenario['knowledge_at'] = '2026-11-06T12:00:00Z';
    $scenario['facts']['rtc.split_payment_ledger'] = [splitB34Ledger($scenario['facts'], $scenario['knowledge_at'])];

    return [$scenario, refresh156($knowledge)];
}

/** @return array<string, mixed> */
function splitB34SyntheticDeployment(): array
{
    return ['id' => 'SYNTHETIC.B34.SPLIT_DEPLOYMENT', 'status' => 'ACTIVE', 'source_kind' => 'SYNTHETIC_TEST',
        'valid_from' => '2026-01-01', 'published_at' => '2026-09-01T00:00:00Z',
        'source_hash' => hash('sha256', 'B34 synthetic split deployment - not an official act')];
}

/** @param array<string, mixed> $ledger
 * @return array<string, mixed>
 */
function resealB34(array $ledger): array
{
    return sealLedger158($ledger);
}
