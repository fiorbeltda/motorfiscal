<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SplitLedger158;
use App\Modules\Fiscal\ConsumptionReform\SplitPlannedInstallment159;

require_once __DIR__.'/split158_fixture.php';

/** @param array<string, mixed> $transaction
 * @return array<string, mixed>
 */
function planned159(array $transaction, string $at): array
{
    $planned = array_intersect_key($transaction, array_flip(['currency', 'source_kind', 'source_hash', 'evidence_id',
        'operation_id', 'document_id', 'supplier_id', 'acquirer_id', 'transaction_id',
        'known_at', 'operation_at', 'original_amount', 'taxes', 'installment']));
    $planned['contract'] = SplitPlannedInstallment159::CONTRACT;
    $planned['planned_initiation_at'] = $at;

    return seal158($planned);
}

/** One paid installment and two supplied future allocations. @return array<string, mixed> */
function split159Ledger(): array
{
    $ledger = split158Ledger();
    $ledger['contract'] = SplitLedger158::SCHEDULE_CONTRACT;
    $ledger['previously_extinguished'] = ['IBS' => '0.00', 'CBS' => '0.00'];
    $ledger['prior_receipt_ids'] = [];
    $ledger['reports'] = $ledger['remittances'] = $ledger['applications'] = $ledger['excess_determinations'] = $ledger['transfers'] = [];
    foreach ([1, 2] as $index) {
        $ledger['transactions'][$index] = planned159($ledger['transactions'][$index], '2026-12-0'.$index.'T12:00:00Z');
    }

    return sealLedger158($ledger);
}
