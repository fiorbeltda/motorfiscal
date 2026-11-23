<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SimplifiedSplitIntegration162;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

require_once __DIR__.'/split162_fixture.php';
require_once __DIR__.'/split160_engine_fixture.php';

/** @return array<string, mixed> */
function split162Deployment(): array
{
    return ['id' => 'SYNTHETIC.A162.SIMPLIFIED.DEPLOYMENT', 'source_kind' => 'SYNTHETIC_TEST', 'procedure' => 'SIMPLIFIED',
        'status' => 'ACTIVE', 'valid_from' => '2026-01-01', 'valid_until' => '2027-01-01', 'published_at' => '2026-01-01T00:00:00Z',
        'revoked_at' => null, 'superseded_at' => null, 'supplier_id' => '*', 'sector' => '*',
        'evidence_id' => 'SYNTHETIC.A162.DEPLOYMENT.PROOF', 'source_hash' => hash('sha256', 'A162 synthetic legal simplified deployment,not phase1')];
}

/** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
function split162EngineScenario(bool $deployment = true, bool $period = false): array
{
    [$scenario] = split160EngineScenario();
    $knowledge = (new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.11.23-a162-draft1.json');
    $scenario['knowledge_at'] = '2026-11-23T20:00:00Z';
    unset($scenario['facts']['rtc.split_payment_ledger']);
    $facts = &$scenario['facts'];
    $facts['rtc.split_procedure'] = 'SIMPLIFIED';
    $facts['rtc.split_nominal_values_identified'] = false;
    $facts['rtc.simplified_sector'] = 'SYNTHETIC.SERVICES';
    $facts['rtc.simplified_split_option'] = 'OMITTED_TAX_IDENTIFICATION';
    $facts['rtc.split_deployment_evidence_id'] = split162Deployment()['id'];
    $calculation = array_replace(split162Calculation(), ['supplier_id' => $facts['rtc.supplier_id'], 'acquirer_id' => $facts['rtc.acquirer_id'],
        'document_id' => $facts['rtc.document_id'], 'payment_transaction_id' => $facts['rtc.payment_transaction_id'],
        'known_at' => $scenario['knowledge_at'], 'operation_on' => $facts['rtc.operation_date'], 'originated_at' => $facts['rtc.operation_date'].'T13:00:00Z',
        'settled_at' => '2026-09-26T12:00:00Z', 'operation_value' => '1000.00']);
    $book = null;
    if ($period) {
        $book = split162Period();
        $book = array_replace($book, ['supplier_id' => $facts['rtc.supplier_id'], 'period_start' => '2026-09-01', 'period_end' => '2026-09-30',
            'as_of' => '2026-10-03T16:00:00Z', 'known_at' => $scenario['knowledge_at'], 'period_closed_at' => '2026-10-01T12:00:00Z']);
        foreach ($book['debts'] as $index => &$debt) {
            $day = ['2026-08-01', '2026-09-01', '2026-09-02'][$index];
            $debt['supplier_id'] = $facts['rtc.supplier_id'];
            $debt['document_issued_at'] = $debt['opening_at'] = $day.'T12:00:00Z';
            $debt['operation_on'] = $day;
        }
        unset($debt);
        $debt = $book['debts'][0];
        $debt = array_replace($debt, ['document_id' => $facts['rtc.document_id'], 'operation_id' => $calculation['operation_id'],
            'operation_on' => $facts['rtc.operation_date'], 'document_issued_at' => $facts['rtc.operation_date'].'T12:00:00Z',
            'opening_at' => $facts['rtc.operation_date'].'T12:00:00Z', 'debits' => ['IBS' => '1.00', 'CBS' => '9.00']]);
        $book['debts'][] = $debt;
        $book['document_order'][] = $facts['rtc.document_id'];
        $book['receipts'][0]['supplier_id'] = $facts['rtc.supplier_id'];
        $book['receipts'][0]['period'] = '2026-09';
        $book['receipts'][0]['collected_at'] = '2026-09-27T12:00:00Z';
        $book = seal158($book);
    }
    $facts[SimplifiedSplitIntegration162::FACT] = [['calculation' => seal158($calculation), 'period' => $book]];
    if ($deployment) {
        $knowledge['documents']['rtc_code_catalog'][SimplifiedSplitIntegration162::DEPLOYMENTS] = [split162Deployment()];
    }

    return [$scenario, $knowledge];
}
