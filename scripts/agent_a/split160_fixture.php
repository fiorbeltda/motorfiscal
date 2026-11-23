<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SplitTransaction158;

require_once __DIR__.'/split159_fixture.php';

/** @param array<string, mixed> $transaction
 * @return array<string, mixed>
 */
function history160(array $transaction): array
{
    $input = $transaction;
    $input['contract'] = SplitTransaction158::HISTORY_CONTRACT;
    $input['fiscal_inputs_unchanged'] = false;
    $input['fiscal_revision_history_complete'] = true;
    $input['fiscal_input_version'] = 'SYNTHETIC.A160.V2';
    $base = ['sequence' => 1, 'version' => 'SYNTHETIC.A160.V1', 'effective_at' => $input['initiated_at'],
        'document_id' => $input['document_id'], 'document_issued_at' => $input['document_issued_at'],
        'document_linked_at' => $input['document_link']['linked_at'], 'original_amount' => $input['original_amount'],
        'taxes' => ['IBS' => '0.00', 'CBS' => '0.00'], 'reset_returns' => false, 'reset_evidence_id' => null,
        'evidence_id' => 'SYNTHETIC.A160.FISCAL1', 'source_hash' => hash('sha256', 'A160 supplied initial fiscal data')];
    $next = array_replace($base, ['sequence' => 2, 'version' => $input['fiscal_input_version'],
        'effective_at' => (new DateTimeImmutable($input['initiated_at']))->modify('+1 hour')->format('Y-m-d\TH:i:sP'),
        'taxes' => $input['taxes'], 'reset_returns' => true, 'reset_evidence_id' => 'SYNTHETIC.A160.RESET2',
        'evidence_id' => 'SYNTHETIC.A160.FISCAL2', 'source_hash' => hash('sha256', 'A160 supplied revised fiscal data and reset')]);
    $input['fiscal_revisions'] = [$base, $next];
    foreach ($input['platform_returns'] as &$message) {
        $message['fiscal_input_version'] = $base['version'];
    }
    unset($message);

    return seal158($input);
}

/** @return array<string, mixed> */
function split160Transaction(): array
{
    $input = history160(split158Transaction());
    $message = $input['platform_returns'][0];
    $message = array_replace($message, ['id' => 'SYNTHETIC.A160.NEW.CORRECTION', 'sequence' => 4,
        'fiscal_input_version' => $input['fiscal_input_version'], 'tax' => 'IBS', 'kind' => 'CORRECTED', 'amount' => '8.00',
        'available_at' => '2026-10-02T12:02:01Z', 'processed_at' => '2026-10-02T12:02:01Z']);
    // New revision response processed before the supplied calculation freeze.
    $input['settlement']['calculation_frozen_at'] = '2026-10-02T12:04:00Z';
    $input['platform_returns'][] = $message;

    return seal158($input);
}
