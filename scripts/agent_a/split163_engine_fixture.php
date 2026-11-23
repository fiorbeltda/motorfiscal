<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SimplifiedSplitIntegration162;
use App\Modules\Fiscal\ConsumptionReform\SplitDeadlineIntegration163;
use App\Modules\Fiscal\ConsumptionReform\SplitLedger158;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

require_once __DIR__.'/split163_fixture.php';
require_once __DIR__.'/split162_engine_fixture.php';
require_once __DIR__.'/split161_fixture.php';

/** @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function split163FromRecord(array $row, string $at): array
{
    return ['at' => $row[$at], 'source_hash' => $row['source_hash'], 'evidence_id' => $row['evidence_id']];
}

/** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
function split163EngineScenario(string $mode = 'PSP_REMITTANCE', bool $multiple = false): array
{
    [$scenario] = $mode === 'SIMPLIFIED_EXCESS_TRANSFER' ? split162EngineScenario(true, true) : split160EngineScenario();
    $knowledge = (new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.11.23-a163-draft1.json');
    $scenario['knowledge_at'] = '2026-11-24T12:00:00Z';
    $request = split163Input($mode);
    $request['known_at'] = $request['as_of'] = $scenario['knowledge_at'];
    if ($mode === 'SIMPLIFIED_EXCESS_TRANSFER') {
        $knowledge['documents']['rtc_code_catalog'][SimplifiedSplitIntegration162::DEPLOYMENTS] = [split162Deployment()];
        $book = $scenario['facts'][SimplifiedSplitIntegration162::FACT][0]['period'];
        $book['known_at'] = $scenario['knowledge_at'];
        $calculation = $scenario['facts'][SimplifiedSplitIntegration162::FACT][0]['calculation'];
        $calculation['known_at'] = $scenario['knowledge_at'];
        $scenario['facts'][SimplifiedSplitIntegration162::FACT][0]['calculation'] = seal158($calculation);
        foreach ([1, 2, 0, 3] as $index => $debtIndex) {
            $debt = $book['debts'][$debtIndex];
            $application = split162Event('APP.CBS.'.$index, $index + 1, 'APPLICATION', 'CBS', $debt['debits']['CBS'],
                '2026-10-02T11:00:00Z', $debt['document_id']);
            $application['supplier_id'] = $book['supplier_id'];
            $book['events'][] = $application;
        }
        $event = split162Event('EXCESS.CBS', 5, 'EXCESS_DETERMINATION', 'CBS', '1.00', '2026-10-02T12:00:00Z');
        $event['supplier_id'] = $book['supplier_id'];
        $book['events'][] = $event;
        $scenario['facts'][SimplifiedSplitIntegration162::FACT][0]['period'] = seal158($book);
        $request['events'] = ['apuration_concluded' => ['at' => $book['period_closed_at'],
            'source_hash' => $book['closure_source_hash'], 'evidence_id' => $book['closure_evidence_id']],
            'excess_determined' => split163FromRecord($event, 'at')];
    } else {
        $knowledge['documents']['rtc_transition_catalog']['split_deployments'] = [splitB34SyntheticDeployment()];
        $book = $scenario['facts']['rtc.split_payment_ledger'][0];
        $book['known_at'] = $book['transactions'][0]['known_at'] = $scenario['knowledge_at'];
        $book['contract'] = SplitLedger158::PAYMENTS_CONTRACT;
        $tx = $book['transactions'][0];
        if ($multiple) {
            $book['contract'] = SplitLedger158::PAYMENTS_CONTRACT;
            $boleto = boleto161FromTransaction($tx, ['500.00', '500.00']);
            $book['transactions'][0] = $boleto;
            $tx = $boleto['payments'][0]['transaction'];
            $book['reports'][0]['operation_taxes'] = $book['reports'][0]['batch_taxes'] = ['IBS' => '0.50', 'CBS' => '4.50'];
            unset($book['reports'][0]['transaction_ids']);
            $book['reports'][0]['payment_references'] = [['payment_id' => $boleto['payments'][0]['payment_id'], 'transaction_id' => $tx['transaction_id']]];
            $book['remittances'] = $book['applications'] = [];
        }
        $scenario['facts']['rtc.split_payment_ledger'] = [sealLedger158($book)];
        $request['reference_id'] = $tx['settlement']['receipt_id'];
        $request['events']['financial_settlement'] = split163FromRecord($tx['settlement'], 'settled_at');
        if ($mode === 'PSP_REMITTANCE') {
            $request['events']['segregation_report'] = split163FromRecord($book['reports'][0], 'sent_at');
            $request['performance'] = $multiple ? null : split163FromRecord($book['remittances'][1], 'received_at');
        } else {
            $request['performance'] = split163FromRecord($book['reports'][0], 'sent_at');
        }
    }
    $scenario['facts'][SplitDeadlineIntegration163::FACT] = [seal158($request)];

    return [$scenario, $knowledge];
}
