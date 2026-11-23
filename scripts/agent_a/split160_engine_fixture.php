<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SplitLedger158;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

require_once __DIR__.'/split160_fixture.php';
require_once __DIR__.'/domicile151_fixture.php';
require_once dirname(__DIR__).'/agent_b/split158_engine_fixture.php';

/** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
function split160EngineScenario(): array
{
    [$scenario] = splitB34Scenario();
    $knowledge = (new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.11.23-a160-draft1.json');
    $scenario['knowledge_at'] = '2026-11-23T15:00:00Z';
    $input = array_replace(domicile151Fixture(), ['operation_date' => '2026-09-25', 'supplier_id' => '11111111000191',
        'acquirer_id' => '22222222000191', 'recipient_id' => '22222222000191', 'knowledge_at' => '2026-11-05T12:00:00Z']);
    foreach (['scope_evidence', 'registry_evidence', 'supply_establishment_evidence'] as $key) {
        $record = $input[$key]['record'];
        $record['entity_id'] = '22222222000191';
        if ($key === 'registry_evidence') {
            foreach ($record['establishments'] as $i => $establishment) {
                $record['establishments'][$i]['entity_id'] = '22222222000191';
            }
            $record['establishments'][1]['uf'] = 'SP';
            $record['establishments'][1]['municipality_ibge'] = '3509502';
        }
        $input[$key] = domicile151Envelope($record);
    }
    $scenario['facts']['rtc.destination_domicile_report'] = [domicile151Qualifier()->qualify(domicile151Rebind($input))];
    $ledger = splitB34Ledger($scenario['facts'], $scenario['knowledge_at']);
    $ledger['contract'] = SplitLedger158::LIFECYCLE_CONTRACT;
    $ledger['transactions'][0] = history160($ledger['transactions'][0]);
    $scenario['facts']['rtc.split_payment_ledger'] = [sealLedger158($ledger)];
    $knowledge['documents']['rtc_transition_catalog']['split_deployments'] = [splitB34SyntheticDeployment()];

    return [$scenario, $knowledge];
}
