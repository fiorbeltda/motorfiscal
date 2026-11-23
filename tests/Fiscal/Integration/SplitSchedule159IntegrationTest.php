<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SplitLedger158;
use App\Modules\Fiscal\ConsumptionReform\SplitPaymentIntegrationB34;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use Illuminate\Support\Facades\Artisan;

require_once dirname(__DIR__, 3).'/scripts/agent_a/split159_fixture.php';
require_once dirname(__DIR__, 3).'/scripts/agent_b/split158_engine_fixture.php';
require_once dirname(__DIR__, 3).'/scripts/agent_a/domicile151_fixture.php';

const A159_BUNDLE = 'bundles/consumption-phase4b-2026.11.23-a159-draft1.json';

/** @return array<string, mixed> */
function manifest159(string $path): array
{
    return json_decode((string) file_get_contents(resource_path('fiscal/'.$path)), true, 512, JSON_THROW_ON_ERROR);
}

/** @return array<string, mixed> */
function domicile159(): array
{
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

    return domicile151Qualifier()->qualify(domicile151Rebind($input));
}

/** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
function scenario159(bool $deployment = true): array
{
    [$scenario] = splitB34Scenario();
    $knowledge = (new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'), A159_BUNDLE);
    $scenario['knowledge_at'] = '2026-11-23T15:00:00Z';
    $scenario['facts']['rtc.destination_domicile_report'] = [domicile159()];
    $ledger = splitB34Ledger($scenario['facts'], $scenario['knowledge_at']);
    $ledger['contract'] = SplitLedger158::SCHEDULE_CONTRACT;
    // Split the supplied principal/debits equally; only the first half has been paid.
    $first = $ledger['transactions'][0];
    $first['original_amount'] = $first['installment']['original_amount'] = '500.00';
    $first['taxes'] = $first['installment']['taxes'] = ['IBS' => '0.50', 'CBS' => '4.50'];
    $first['installment']['count'] = 2;
    $first['settlement']['paid_amount'] = '500.00';
    $second = $first;
    $second['transaction_id'] = 'SYNTHETIC.A159.FUTURE';
    $second['installment']['number'] = 2;
    $ledger['transactions'] = [$first, planned159($second, '2026-12-01T12:00:00Z')];
    $ledger['reports'] = $ledger['remittances'] = $ledger['applications'] = [];
    $scenario['facts']['rtc.split_payment_ledger'] = [sealLedger158($ledger)];
    if ($deployment) {
        $knowledge['documents']['rtc_transition_catalog']['split_deployments'] = [splitB34SyntheticDeployment()];
    }

    return [$scenario, $knowledge];
}

it('activates the complete future schedule from the installed A159 bundle', function (bool $integrated): void {
    [$scenario, $knowledge] = scenario159();
    $result = evaluate156($scenario, $knowledge, $integrated);
    if ($integrated) {
        $events = array_values(array_filter($result['trace'], static fn (array $event): bool => $event['type'] === 'CONSUMPTION_REFORM'));
        $result = $events[0]['payload'];
    }
    $candidate = array_column($result['trace'], null, 'stage')['SYNTHETIC_ARITHMETIC156'];
    expect($candidate['candidate_status'])->toBe('VALID')
        ->and($result['decision'])->toBe([])->and($result['reason_codes'])->toContain('RTC_SYNTHETIC_TEST_ONLY');
    $report = $candidate['candidate_decision']['collection']['split_ledger158'];
    expect($report['planned_installment_count'])->toBe(1)
        ->and($report['taxes']['IBS']['segregated'])->toBe('0.50')
        ->and($report['taxes']['CBS']['segregated'])->toBe('4.50')
        ->and($report['taxes']['IBS']['remaining'])->toBe('1.00')
        ->and((new SplitLedger158)->replaySchedule($report))->toBeTrue();
})->with([false, true]);

it('preserves the official deployment guard in the installed bundle', function (): void {
    [$scenario, $knowledge] = scenario159(false);
    $result = evaluate156($scenario, $knowledge);
    $candidate = array_column($result['trace'], null, 'stage')['SYNTHETIC_ARITHMETIC156'];
    expect($candidate['candidate_reasons'])->toContain('RTC_SPLIT_OFFICIAL_DEPLOYMENT_NOT_PROVEN');
});

it('keeps the legacy bundle from silently accepting the A159 contract', function (): void {
    [$scenario, $knowledge] = scenario159();
    $knowledge['documents']['rtc_code_catalog'][SplitPaymentIntegrationB34::KEY] = SplitPaymentIntegrationB34::POLICY;
    $result = evaluate156($scenario, $knowledge);
    $candidate = array_column($result['trace'], null, 'stage')['SYNTHETIC_ARITHMETIC156'];
    expect($candidate['candidate_reasons'])->toContain('RTC_SPLIT_LEDGER_CONTRACT_CONFLICT');
});

it('loads an exact policy and preserves all other B38 catalog resources', function (): void {
    $new = manifest159(A159_BUNDLE);
    $old = manifest159('bundles/consumption-phase4b-2026.11.06-b38-draft1.json');
    expect($new['predecessor_bundle_id'])->toBe($old['bundle_id'])
        ->and(array_keys(array_diff_assoc($new['content_hashes'], $old['content_hashes'])))
        ->toEqualCanonicalizing(['rtc_code_catalog', 'questions'])
        ->and((new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'), A159_BUNDLE)['documents']['rtc_code_catalog'][SplitPaymentIntegrationB34::KEY])
        ->toBe(SplitPaymentIntegrationB34::SCHEDULE_POLICY);
});

it('does not present a planned identifier as the payment transaction of the operation', function (): void {
    [$scenario, $knowledge] = scenario159();
    $scenario['facts']['rtc.payment_transaction_id'] = 'SYNTHETIC.A159.FUTURE';
    $result = evaluate156($scenario, $knowledge);
    $candidate = array_column($result['trace'], null, 'stage')['SYNTHETIC_ARITHMETIC156'];
    expect($candidate['candidate_reasons'])->toContain('RTC_SPLIT_LEDGER_ANCHOR_NOT_INITIATED');
});

it('exposes deterministic CLI schedule assessment and refuses tampering', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'split159-');
    try {
        $input = split159Ledger();
        file_put_contents($path, json_encode($input, JSON_THROW_ON_ERROR));
        expect(Artisan::call('fiscal:rtc:assess-split-payment', ['input' => $path, '--mode' => 'schedule']))->toBe(0);
        $first = Artisan::output();
        $result = json_decode($first, true, 512, JSON_THROW_ON_ERROR);
        expect($result['replay_matches'])->toBeTrue()->and($result['report']['planned_installment_count'])->toBe(2);
        Artisan::call('fiscal:rtc:assess-split-payment', ['input' => $path, '--mode' => 'schedule']);
        expect(Artisan::output())->toBe($first);
        $input['transactions'][1]['planned_initiation_at'] = '2026-10-01T00:00:00Z';
        file_put_contents($path, json_encode($input, JSON_THROW_ON_ERROR));
        expect(Artisan::call('fiscal:rtc:assess-split-payment', ['input' => $path, '--mode' => 'schedule']))->toBe(1);
    } finally {
        unlink($path);
    }
});
