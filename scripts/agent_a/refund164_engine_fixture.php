<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\AcquirerCollection157;
use App\Modules\Fiscal\ConsumptionReform\RefundIntegration164;
use App\Modules\Fiscal\ConsumptionReform\SplitLedger158;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

require_once __DIR__.'/refund164_fixture.php';
require_once __DIR__.'/split163_engine_fixture.php';

/** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
function refund164EngineScenario(string $procedure = 'STANDARD_FALLBACK'): array
{
    [$reference] = split163EngineScenario();
    $knowledge = (new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.11.23-a164-draft1.json');
    if ($procedure === 'ACQUIRER_EXCESS') {
        [$scenario] = collection157Scenario();
        $scenario['knowledge_at'] = '2026-11-24T12:00:00Z';
        $scenario['facts']['rtc.destination_domicile_report'] = $reference['facts']['rtc.destination_domicile_report'];
        $book = $scenario['facts']['rtc.acquirer_collection_ledger'][0];
        $book['known_at'] = $scenario['knowledge_at'];
        $book = seal157($book);
        $scenario['facts']['rtc.acquirer_collection_ledger'] = [$book];
        $context = (new AcquirerCollection157)->reconcile($book);
    } else {
        $scenario = $reference;
        unset($scenario['facts']['rtc.split_deadline_requests']);
        $knowledge['documents']['rtc_transition_catalog']['split_deployments'] = [splitB34SyntheticDeployment()];
        $book = $scenario['facts']['rtc.split_payment_ledger'][0];
        foreach ($book['applications'] as &$application) {
            $application['modality'] = 'COMPENSATION';
            $application['remittance_id'] = null;
            $application['applied_at'] = '2026-09-27T12:00:00Z';
        }
        unset($application);
        $binding = array_intersect_key($book, array_flip(['operation_id', 'document_id', 'supplier_id', 'acquirer_id']));
        foreach ($book['remittances'] as $remit) {
            $book['excess_determinations'][] = $binding + ['id' => 'EXCESS.'.$remit['tax'], 'tax' => $remit['tax'],
                'remittance_id' => $remit['id'], 'amount' => $remit['amount'], 'determined_at' => '2026-09-28T14:00:00Z',
                'authority_receipt_id' => 'SYNTHETIC.EXCESS.'.$remit['tax'], 'evidence_id' => 'SYNTHETIC.EXCESS.PROOF.'.$remit['tax'],
                'source_hash' => hash('sha256', 'A164 synthetic engine excess '.$remit['tax'])];
        }
        $book['transfers'][] = $binding + ['id' => 'TRANSFER.IBS', 'tax' => 'IBS', 'determination_id' => 'EXCESS.IBS',
            'beneficiary_id' => $book['supplier_id'], 'amount' => '0.40', 'transferred_at' => '2026-10-02T12:00:00Z',
            'authority_receipt_id' => 'SYNTHETIC.TRANSFER.IBS', 'evidence_id' => 'SYNTHETIC.TRANSFER.PROOF.IBS',
            'source_hash' => hash('sha256', 'A164 synthetic engine transfer')];
        $book = sealLedger158($book);
        $scenario['facts']['rtc.split_payment_ledger'] = [$book];
        $context = (new SplitLedger158)->reconcilePayments($book);
    }
    $input = refund164FromContext($context, $procedure);
    foreach ($input['requests'] as &$request) {
        if ($procedure === 'STANDARD_FALLBACK') {
            $request['notice']['consultation_failure']['at'] = '2026-09-26T12:01:00Z';
            $request['notice']['correct_linked_at'] = '2026-09-27T12:00:00Z';
            $request['notice']['information_received_at'] = '2026-09-27T12:00:00Z';
            $request['notice']['recorded_at'] = '2026-09-28T15:00:00Z';
        }
    }
    unset($request);
    $scenario['facts'][RefundIntegration164::FACT] = [seal158($input)];

    return [$scenario, $knowledge];
}
