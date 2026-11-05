<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SplitLedger158;
use App\Modules\Fiscal\ConsumptionReform\SplitPayment158;
use App\Modules\Fiscal\ConsumptionReform\SplitTransaction158;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

/** @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function seal158(array $input): array
{
    unset($input['input_hash']);
    $input['input_hash'] = CanonicalJson::hash($input);

    return $input;
}

/** Synthetic amounts are inputs to arithmetic, never official rates or platform responses.
 * @return array<string, mixed>
 */
function split158Calculation(): array
{
    return seal158(['contract' => SplitPayment158::CONTRACT, 'manual_hash' => SplitPayment158::OPERATIONS_MANUAL_HASH,
        'procedure' => 'STANDARD', 'currency' => 'BRL', 'source_kind' => 'SYNTHETIC_TEST',
        'transaction_id' => 'SYNTHETIC.A158.T1', 'arrangement' => 'BOLETO', 'originator' => 'RECEIVER',
        'original_amount' => '100.00', 'paid_amount' => '33.33',
        'taxes' => ['IBS' => ['informed' => '10.00'], 'CBS' => ['informed' => '20.00']]]);
}

/** @return array<string, mixed> */
function split158Transaction(): array
{
    $input = ['contract' => SplitTransaction158::CONTRACT,
        'operations_manual_hash' => SplitPayment158::OPERATIONS_MANUAL_HASH,
        'times_manual_hash' => SplitTransaction158::TIMES_MANUAL_HASH,
        'procedure' => 'STANDARD', 'currency' => 'BRL', 'source_kind' => 'SYNTHETIC_TEST',
        'source_hash' => hash('sha256', 'A158 synthetic transaction evidence'), 'evidence_id' => 'SYNTHETIC.TX.PROOF',
        'transaction_id' => 'SYNTHETIC.TX1', 'operation_id' => 'SYNTHETIC.OP1', 'document_id' => 'SYNTHETIC.DF1',
        'supplier_id' => 'SYNTHETIC.S1', 'acquirer_id' => 'SYNTHETIC.A1', 'receiver_psp_id' => 'SYNTHETIC.PSP1',
        'original_payer_id' => 'SYNTHETIC.A1', 'effective_payer_id' => 'SYNTHETIC.EFFECTIVE.PAYER1',
        'supplier_kind' => 'PJ', 'original_payer_kind' => 'PJ', 'effective_payer_kind' => 'PJ',
        'supplier_cnpj_registered' => true, 'original_payer_cnpj_registered' => true, 'open_finance_initiated' => false,
        'arrangement' => 'BOLETO', 'originator' => 'RECEIVER', 'original_amount' => '100.00',
        'taxes' => ['IBS' => '10.00', 'CBS' => '20.00'],
        'known_at' => '2026-10-04T16:00:00Z', 'operation_at' => '2026-10-01T09:00:00Z',
        'document_issued_at' => '2026-10-01T09:00:00Z', 'initiated_at' => '2026-10-01T12:00:00Z',
        'fiscal_input_version' => 'SYNTHETIC.V1', 'fiscal_inputs_unchanged' => true, 'platform_history_complete' => true,
        'document_link' => ['channel' => 'ARRANGEMENT', 'linked_at' => '2026-10-01T10:00:00Z',
            'document_id' => 'SYNTHETIC.DF1', 'transaction_id' => 'SYNTHETIC.TX1', 'evidence_id' => 'SYNTHETIC.LINK'],
        'installment' => ['number' => 1, 'count' => 3, 'operation_id' => 'SYNTHETIC.OP1', 'document_id' => 'SYNTHETIC.DF1',
            'original_amount' => '100.00', 'taxes' => ['IBS' => '10.00', 'CBS' => '20.00'], 'allocation_evidence_id' => 'SYNTHETIC.INSTALLMENT'],
        'receivable_advance' => ['transaction_id' => 'SYNTHETIC.TX1', 'at' => '2026-10-01T16:00:00Z', 'amount' => '80.00', 'evidence_id' => 'SYNTHETIC.ADVANCE'],
        'settlement' => ['transaction_id' => 'SYNTHETIC.TX1', 'milestone' => 'BOLETO_PAYMENT_DISCHARGE_COMPLETE',
            'milestone_at' => '2026-10-02T12:03:00Z', 'noncontractual_clear_at' => '2026-10-02T12:05:00Z',
            'calculation_frozen_at' => '2026-10-02T12:02:00Z', 'settled_at' => '2026-10-02T12:05:00Z', 'paid_amount' => '33.33',
            'receipt_id' => 'SYNTHETIC.SETTLED', 'evidence_id' => 'SYNTHETIC.SETTLEMENT.PROOF', 'source_hash' => hash('sha256', 'A158 synthetic settlement')],
        'platform_returns' => []];
    foreach ([['IBS', 'CORRECTED', '12.00', '2026-10-01T15:00:00Z', '2026-10-01T15:01:00Z'],
        ['CBS', 'OUTSTANDING', '5.00', '2026-10-02T12:00:00Z', '2026-10-02T12:01:00Z'],
        ['IBS', 'OUTSTANDING', '1.00', '2026-10-02T12:02:00Z', '2026-10-02T12:03:00Z']] as $index => $row) {
        $input['platform_returns'][] = ['id' => 'SYNTHETIC.MSG'.$index, 'sequence' => $index + 1,
            'document_id' => $input['document_id'], 'transaction_id' => $input['transaction_id'],
            'fiscal_input_version' => $input['fiscal_input_version'], 'evidence_id' => 'SYNTHETIC.MSG.PROOF'.$index,
            'source_hash' => hash('sha256', 'A158 synthetic platform message '.$index),
            'tax' => $row[0], 'kind' => $row[1], 'amount' => $row[2], 'available_at' => $row[3], 'processed_at' => $row[4]];
    }

    return seal158($input);
}

/** @return array<string, mixed> */
function split158Ledger(): array
{
    $seed = split158Transaction();
    $input = ['contract' => SplitLedger158::CONTRACT, 'currency' => 'BRL', 'source_kind' => 'SYNTHETIC_TEST',
        'source_hash' => hash('sha256', 'A158 synthetic ledger'), 'evidence_id' => 'SYNTHETIC.LEDGER.PROOF',
        'known_at' => '2026-10-06T16:00:00Z', 'history_as_of' => '2026-10-01T12:00:00Z',
        'operation_id' => $seed['operation_id'], 'document_id' => $seed['document_id'],
        'supplier_id' => $seed['supplier_id'], 'acquirer_id' => $seed['acquirer_id'],
        'operation_amount' => '300.00', 'debits' => ['IBS' => '30.00', 'CBS' => '60.00'],
        'previously_extinguished' => ['IBS' => '5.00', 'CBS' => '10.00'], 'prior_receipt_ids' => ['SYNTHETIC.PRIOR.IBS', 'SYNTHETIC.PRIOR.CBS'],
        'schedule_complete' => true, 'reports_complete' => true, 'remittances_complete' => true,
        'applications_complete' => true, 'excess_complete' => true, 'transfers_complete' => true,
        'transactions' => [], 'reports' => [], 'remittances' => [], 'applications' => [], 'excess_determinations' => [], 'transfers' => []];
    $binding = array_intersect_key($input, array_flip(['operation_id', 'document_id', 'supplier_id', 'acquirer_id']));
    for ($number = 1; $number <= 3; $number++) {
        $transaction = $seed;
        $transaction['known_at'] = $input['known_at'];
        $transaction['transaction_id'] = 'SYNTHETIC.TX'.$number;
        $transaction['document_link']['transaction_id'] = $transaction['transaction_id'];
        $transaction['receivable_advance']['transaction_id'] = $transaction['transaction_id'];
        $transaction['settlement']['transaction_id'] = $transaction['transaction_id'];
        $transaction['settlement']['receipt_id'] = 'SYNTHETIC.SETTLED'.$number;
        $transaction['settlement']['paid_amount'] = '100.00';
        $transaction['installment']['number'] = $number;
        $transaction['platform_returns'] = [];
        $input['transactions'][] = seal158($transaction);
    }
    foreach ([['R1', ['SYNTHETIC.TX1', 'SYNTHETIC.TX2'], '20.00', '40.00', '40.00', '80.00', '60.00', '120.00', '16:00:00'],
        ['R2', ['SYNTHETIC.TX3'], '10.00', '20.00', '30.00', '60.00', '40.00', '80.00', '21:00:00']] as $row) {
        $input['reports'][] = ['id' => $row[0], 'receiver_psp_id' => $seed['receiver_psp_id'], 'arrangement' => 'BOLETO',
            'reference_date' => '2026-10-02', 'sent_at' => '2026-10-02T'.$row[8].'Z',
            'authority_acknowledgement_id' => 'SYNTHETIC.ACK.'.$row[0], 'transaction_ids' => $row[1],
            'operation_taxes' => ['IBS' => $row[2], 'CBS' => $row[3]],
            'other_operation_taxes' => ['IBS' => $row[4], 'CBS' => $row[5]], 'batch_taxes' => ['IBS' => $row[6], 'CBS' => $row[7]],
            'source_hash' => hash('sha256', 'A158 synthetic report '.$row[0]), 'evidence_id' => 'SYNTHETIC.REPORT.PROOF.'.$row[0]];
    }
    foreach ([['IBS', '100.00', 'STR', '25.00', '5.00'], ['CBS', '200.00', 'TES', '50.00', '10.00']] as $row) {
        $tax = $row[0];
        $input['remittances'][] = ['id' => 'REM.'.$tax, 'tax' => $tax, 'receiver_psp_id' => $seed['receiver_psp_id'],
            'arrangement' => 'BOLETO', 'reference_date' => '2026-10-02', 'report_ids' => ['R1', 'R2'], 'amount' => $row[1], 'rail' => $row[2],
            'received_at' => '2026-10-05T12:00:00Z', 'authority_receipt_id' => 'SYNTHETIC.REMIT.RECEIPT.'.$tax,
            'source_hash' => hash('sha256', 'A158 synthetic remittance '.$tax), 'evidence_id' => 'SYNTHETIC.REMIT.PROOF.'.$tax];
        $input['applications'][] = $binding + ['id' => 'APP.'.$tax, 'tax' => $tax, 'modality' => 'SPLIT_PAYMENT', 'applied' => true,
            'remittance_id' => 'REM.'.$tax, 'amount' => $row[3], 'applied_at' => '2026-10-05T13:00:00Z',
            'application_receipt_id' => 'SYNTHETIC.APP.RECEIPT.'.$tax,
            'source_hash' => hash('sha256', 'A158 synthetic application '.$tax), 'evidence_id' => 'SYNTHETIC.APP.PROOF.'.$tax];
        $input['excess_determinations'][] = $binding + ['id' => 'EXCESS.'.$tax, 'tax' => $tax, 'remittance_id' => 'REM.'.$tax,
            'amount' => $row[4], 'determined_at' => '2026-10-05T14:00:00Z', 'authority_receipt_id' => 'SYNTHETIC.EXCESS.RECEIPT.'.$tax,
            'source_hash' => hash('sha256', 'A158 synthetic excess '.$tax), 'evidence_id' => 'SYNTHETIC.EXCESS.PROOF.'.$tax];
    }
    $input['transfers'][] = $binding + ['id' => 'TRANSFER.IBS', 'tax' => 'IBS', 'determination_id' => 'EXCESS.IBS',
        'beneficiary_id' => $input['supplier_id'], 'amount' => '2.00', 'transferred_at' => '2026-10-06T12:00:00Z',
        'authority_receipt_id' => 'SYNTHETIC.TRANSFER.RECEIPT.IBS',
        'source_hash' => hash('sha256', 'A158 synthetic excess transfer'), 'evidence_id' => 'SYNTHETIC.TRANSFER.PROOF.IBS'];

    return seal158($input);
}

/** @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function sealLedger158(array $input): array
{
    foreach ($input['transactions'] as &$transaction) {
        $transaction = seal158($transaction);
    }
    unset($transaction);

    return seal158($input);
}
