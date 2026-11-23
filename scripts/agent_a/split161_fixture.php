<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SplitBoletoPayments161;
use App\Modules\Fiscal\ConsumptionReform\SplitLedger158;

require_once __DIR__.'/split160_fixture.php';

/** @return array<string, mixed> */
function split161Boleto(bool $integral = true): array
{
    $base = split158Transaction();
    $base['installment']['count'] = 1;

    return boleto161FromTransaction($base, ['33.33', '66.67'], $integral);
}

/** @param array<string, mixed> $base
 * @param  array{0: string, 1: string}  $amounts
 * @return array<string, mixed>
 */
function boleto161FromTransaction(array $base, array $amounts, bool $integral = true): array
{
    $base['platform_returns'] = [];
    $payments = [];
    $firstDay = new DateTimeImmutable($base['settlement']['settled_at']);
    foreach ($amounts as $index => $amount) {
        $day = $firstDay->modify('+'.$index.' day')->format('Y-m-d');
        $transaction = $base;
        $transaction['known_at'] = $day.'T16:00:00Z';
        $transaction['settlement'] = array_replace($transaction['settlement'], [
            'milestone_at' => $day.'T12:03:00Z', 'noncontractual_clear_at' => $day.'T12:05:00Z',
            'calculation_frozen_at' => $day.'T12:02:00Z', 'settled_at' => $day.'T12:05:00Z',
            'paid_amount' => $amount, 'receipt_id' => 'SYNTHETIC.A161.RECEIPT'.$index]);
        if ($index === 1 && $integral) {
            $transaction['platform_window_closed_at'] = $day.'T12:01:00Z';
            $transaction['platform_window_close_reason'] = 'FULL_PAYMENT_REPORTED';
            $transaction['platform_window_close_evidence_id'] = 'SYNTHETIC.A161.INTEGRAL.PROOF';
        }
        $payments[] = ['payment_id' => 'SYNTHETIC.A161.PAYMENT'.$index, 'sequence' => $index + 1,
            'integral_payment' => $index === 1 && $integral, 'transaction' => seal158($transaction)];
    }
    $snapshot = $payments[1]['transaction'];
    $snapshot['known_at'] = $base['known_at'];
    $snapshot['settlement'] = null;
    $input = array_intersect_key($snapshot, array_flip(['currency', 'source_kind', 'operation_id', 'document_id',
        'supplier_id', 'acquirer_id', 'transaction_id', 'receiver_psp_id', 'operation_at', 'known_at',
        'original_amount', 'taxes', 'installment']));
    $input += ['contract' => SplitBoletoPayments161::CONTRACT, 'source_hash' => hash('sha256', 'A161 supplied boleto payment history'),
        'evidence_id' => 'SYNTHETIC.A161.BOLETO.PROOF', 'history_complete' => true,
        'current_snapshot' => seal158($snapshot), 'payments' => $payments];

    return seal158($input);
}

/** @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function sealBoleto161(array $input): array
{
    $input['current_snapshot'] = seal158($input['current_snapshot']);
    foreach ($input['payments'] as &$payment) {
        $payment['transaction'] = seal158($payment['transaction']);
    }
    unset($payment);

    return seal158($input);
}

/** @return array<string, mixed> */
function split161Ledger(): array
{
    $input = split159Ledger();
    $input['contract'] = SplitLedger158::PAYMENTS_CONTRACT;
    $boleto = split161Boleto();
    $boleto['known_at'] = $boleto['current_snapshot']['known_at'] = $input['known_at'];
    $boleto['installment']['count'] = $boleto['current_snapshot']['installment']['count'] = 3;
    foreach ($boleto['payments'] as &$payment) {
        $payment['transaction']['installment']['count'] = 3;
    }
    unset($payment);
    $input['transactions'][0] = sealBoleto161($boleto);
    $binding = array_intersect_key($input, array_flip(['operation_id', 'document_id', 'supplier_id', 'acquirer_id']));
    foreach ([['R1', 0, '2026-10-02', '2026-10-03', '3.33', '6.66'], ['R2', 1, '2026-10-03', '2026-10-04', '6.66', '13.33']] as [$id, $index, $day, $next, $ibs, $cbs]) {
        $input['reports'][] = ['id' => $id, 'receiver_psp_id' => $boleto['receiver_psp_id'], 'arrangement' => 'BOLETO',
            'reference_date' => $day, 'sent_at' => $day.'T16:00:00Z', 'authority_acknowledgement_id' => 'SYNTHETIC.A161.ACK.'.$id,
            'payment_references' => [['transaction_id' => $boleto['transaction_id'], 'payment_id' => $boleto['payments'][$index]['payment_id']]],
            'operation_taxes' => ['IBS' => $ibs, 'CBS' => $cbs], 'other_operation_taxes' => ['IBS' => '0.00', 'CBS' => '0.00'],
            'batch_taxes' => ['IBS' => $ibs, 'CBS' => $cbs], 'source_hash' => hash('sha256', 'A161 supplied report '.$id),
            'evidence_id' => 'SYNTHETIC.A161.REPORT.'.$id];
        foreach (['IBS' => $ibs, 'CBS' => $cbs] as $tax => $amount) {
            $remitId = 'A161.REM.'.$id.'.'.$tax;
            $input['remittances'][] = ['id' => $remitId, 'tax' => $tax, 'receiver_psp_id' => $boleto['receiver_psp_id'],
                'arrangement' => 'BOLETO', 'reference_date' => $day, 'report_ids' => [$id], 'amount' => $amount,
                'rail' => $tax === 'IBS' ? 'STR' : 'TES', 'received_at' => $next.'T10:00:00Z',
                'authority_receipt_id' => 'SYNTHETIC.RECEIPT.'.$remitId, 'source_hash' => hash('sha256', $remitId), 'evidence_id' => 'SYNTHETIC.PROOF.'.$remitId];
            $input['applications'][] = $binding + ['id' => 'APP.'.$id.'.'.$tax, 'tax' => $tax, 'modality' => 'SPLIT_PAYMENT', 'applied' => true,
                'remittance_id' => $remitId, 'amount' => $amount, 'applied_at' => $next.'T11:00:00Z',
                'application_receipt_id' => 'SYNTHETIC.APP.RECEIPT.'.$id.'.'.$tax,
                'source_hash' => hash('sha256', 'A161 supplied application '.$id.$tax), 'evidence_id' => 'SYNTHETIC.APP.PROOF.'.$id.'.'.$tax];
        }
    }

    return sealLedger158($input);
}
