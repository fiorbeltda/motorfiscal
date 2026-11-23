<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SimplifiedSplitCalculation162;
use App\Modules\Fiscal\ConsumptionReform\SimplifiedSplitPeriod162;

require_once __DIR__.'/split158_fixture.php';

/** Synthetic percentages and rounding only; not a published official parameter. @return array<string, mixed> */
function split162Parameter(): array
{
    return ['id' => 'SYNTHETIC.A162.PERCENTAGES', 'version' => 'SYNTHETIC.A162.V1', 'environment' => 'SANDBOX', 'source_kind' => 'SYNTHETIC_TEST', 'status' => 'ACTIVE',
        'published_at' => '2026-01-01T00:00:00Z', 'valid_from' => '2026-01-01', 'valid_until' => '2027-01-01',
        'revoked_at' => null, 'superseded_at' => null, 'supplier_id' => '*', 'sector' => '*',
        'authorities' => ['IBS' => 'CGIBS', 'CBS' => 'RFB'], 'percentages' => ['IBS' => '2.5', 'CBS' => '7.5'],
        'rounding' => 'TRUNCATE_TO_CENT', 'selection_basis' => 'FINANCIAL_SETTLEMENT', 'source_hash' => hash('sha256', 'A162 synthetic parameter and rounding'),
        'evidence_id' => 'SYNTHETIC.A162.PARAMETER.PROOF'];
}

/** @return array<string, mixed> */
function split162Calculation(): array
{
    return seal158(['contract' => SimplifiedSplitCalculation162::CONTRACT, 'currency' => 'BRL', 'procedure' => 'SIMPLIFIED',
        'source_kind' => 'SYNTHETIC_TEST', 'source_hash' => hash('sha256', 'A162 synthetic calculation'), 'evidence_id' => 'SYNTHETIC.A162.CALC.PROOF',
        'supplier_id' => 'SYNTHETIC.S1', 'acquirer_id' => 'SYNTHETIC.A1', 'acquirer_regime' => 'REGULAR', 'sector' => 'SYNTHETIC.SERVICES',
        'operation_id' => 'SYNTHETIC.OP1', 'document_id' => 'SYNTHETIC.DF1', 'payment_transaction_id' => 'SYNTHETIC.PAY1',
        'operation_on' => '2026-10-01', 'known_at' => '2026-11-23T16:00:00Z', 'originated_at' => '2026-10-01T12:00:00Z', 'settled_at' => '2026-10-02T12:00:00Z',
        'nominal_values_identified' => false, 'option' => 'OMITTED_TAX_IDENTIFICATION', 'option_evidence_id' => null,
        'operation_value' => '1000.00', 'operation_basis' => '333.33', 'allocation_evidence_id' => 'SYNTHETIC.A162.ALLOCATION',
        'parameter_id' => 'SYNTHETIC.A162.PERCENTAGES', 'synthetic_parameter' => split162Parameter(),
        'mandatory_act_id' => null, 'synthetic_mandatory_act' => null]);
}

/** @return array<string, mixed> */
function split162Period(bool $closed = true): array
{
    $input = ['contract' => SimplifiedSplitPeriod162::CONTRACT, 'currency' => 'BRL', 'source_kind' => 'SYNTHETIC_TEST',
        'source_hash' => hash('sha256', 'A162 synthetic period book'), 'evidence_id' => 'SYNTHETIC.A162.PERIOD.PROOF',
        'supplier_id' => 'SYNTHETIC.S1', 'period_start' => '2026-10-01', 'period_end' => '2026-10-31',
        'as_of' => $closed ? '2026-11-03T16:00:00Z' : '2026-10-20T16:00:00Z', 'known_at' => '2026-11-23T16:00:00Z',
        'phase' => $closed ? 'CLOSED' : 'DURING_PERIOD', 'period_closed_at' => $closed ? '2026-11-01T12:00:00Z' : null,
        'closure_evidence_id' => $closed ? 'SYNTHETIC.A162.CLOSE.PROOF' : null,
        'closure_source_hash' => $closed ? hash('sha256', 'A162 synthetic period closure') : null,
        'debts_complete' => true, 'receipts_complete' => true, 'events_complete' => true, 'opening_excludes_current_receipts' => true,
        'ordering_evidence_id' => 'SYNTHETIC.A162.ORDER.PROOF', 'ordering_source_hash' => hash('sha256', 'A162 supplied document order'),
        'document_order' => ['SYNTHETIC.OLD.REGULAR', 'SYNTHETIC.CURRENT.NONREG1', 'SYNTHETIC.CURRENT.NONREG2'],
        'debts' => [], 'receipts' => [], 'events' => []];
    foreach ([['SYNTHETIC.OLD.REGULAR', '2026-09-01', 'REGULAR', '10.00', '20.00'],
        ['SYNTHETIC.CURRENT.NONREG1', '2026-10-01', 'NON_REGULAR', '5.00', '10.00'],
        ['SYNTHETIC.CURRENT.NONREG2', '2026-10-02', 'NON_REGULAR', '5.00', '10.00']] as [$id, $day, $regime, $ibs, $cbs]) {
        $input['debts'][] = ['document_id' => $id, 'operation_id' => 'OP.'.$id, 'supplier_id' => $input['supplier_id'],
            'acquirer_regime' => $regime, 'document_issued_at' => $day.'T12:00:00Z', 'opening_at' => $day.'T12:00:00Z', 'operation_on' => $day,
            'debits' => ['IBS' => $ibs, 'CBS' => $cbs], 'previously_extinguished' => ['IBS' => '0.00', 'CBS' => '0.00'], 'prior_receipt_ids' => [],
            'source_hash' => hash('sha256', 'A162 supplied debt '.$id), 'evidence_id' => 'PROOF.'.$id];
    }
    $input['receipts'][] = ['id' => 'SYNTHETIC.A162.CASH', 'supplier_id' => $input['supplier_id'], 'period' => '2026-10',
        'received' => true, 'collected_at' => '2026-10-05T12:00:00Z', 'amounts' => ['IBS' => '25.00', 'CBS' => '50.00'],
        'authority_receipt_id' => 'SYNTHETIC.A162.CASH.RECEIPT', 'source_hash' => hash('sha256', 'A162 supplied receipt'), 'evidence_id' => 'SYNTHETIC.A162.CASH.PROOF'];

    return seal158($input);
}

/** @return array<string, mixed> */
function split162Event(string $id, int $sequence, string $kind, string $tax, string $amount, string $at, ?string $document = null, ?string $determination = null): array
{
    return ['id' => $id, 'sequence' => $sequence, 'kind' => $kind, 'tax' => $tax, 'amount' => $amount, 'at' => $at,
        'supplier_id' => 'SYNTHETIC.S1', 'document_id' => $document, 'determination_id' => $determination,
        'beneficiary_id' => $kind === 'TRANSFER' ? 'SYNTHETIC.S1' : null, 'authority_receipt_id' => 'RECEIPT.'.$id,
        'source_hash' => hash('sha256', 'A162 supplied event '.$id), 'evidence_id' => 'PROOF.'.$id];
}
