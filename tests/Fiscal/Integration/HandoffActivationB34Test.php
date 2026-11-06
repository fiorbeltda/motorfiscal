<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SplitPaymentIntegrationB34;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

require_once dirname(__DIR__, 3).'/scripts/agent_b/split158_engine_fixture.php';
require_once dirname(__DIR__, 3).'/scripts/agent_a/domicile151_fixture.php';

// B34: installed bundle that integrates the A158 split-payment ledger behind the `split_payment_a158` gate.
const B34_BUNDLE = 'bundles/consumption-phase4b-2026.11.06-b34-draft1.json';
const B34_PREVIOUS = 'bundles/consumption-phase4b-2026.11.06-b33-draft1.json';

/** @return array<string, mixed> */
function b34Manifest(string $path = B34_BUNDLE): array
{
    return json_decode((string) file_get_contents(resource_path('fiscal/'.$path)), true, 512, JSON_THROW_ON_ERROR);
}

/** @return array<string, mixed> */
function b34Knowledge(string $bundle = B34_BUNDLE): array
{
    return (new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'), $bundle);
}

/** A151 domicile report for the scenario parties (required by the B27 gate since bundle B27). @return array<string, mixed> */
function b34DomicileReport(string $knownAt): array
{
    $input = array_replace(domicile151Fixture(), ['operation_date' => '2026-09-25', 'supplier_id' => '11111111000191',
        'acquirer_id' => '22222222000191', 'recipient_id' => '22222222000191', 'knowledge_at' => $knownAt]);
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

/** Split scenario on the installed bundle; optionally with an in-memory SYNTHETIC deployment row. @return array<string, mixed> */
function b34Evaluate(bool $syntheticDeployment, string $bundle = B34_BUNDLE): array
{
    [$scenario] = splitB34Scenario();
    $knowledge = b34Knowledge($bundle);
    if ($syntheticDeployment) {
        $knowledge['documents']['rtc_transition_catalog']['split_deployments'] = [splitB34SyntheticDeployment()];
    }
    $scenario['knowledge_at'] = max($scenario['knowledge_at'], b34Manifest($bundle)['knowledge_available_at']);
    $scenario['facts']['rtc.destination_domicile_report'] = [b34DomicileReport('2026-11-05T12:00:00Z')];
    $scenario['facts']['rtc.split_payment_ledger'] = [splitB34Ledger($scenario['facts'], $scenario['knowledge_at'])];

    return evaluate156($scenario, $knowledge);
}

it('forks bundle B33 changing only the code catalog, facts, questions and production controls', function (): void {
    $new = b34Manifest();
    $old = b34Manifest(B34_PREVIOUS);
    expect($new['predecessor_bundle_id'])->toBe($old['bundle_id'])
        ->and(array_keys(array_diff_assoc($new['content_hashes'], $old['content_hashes'])))
        ->toEqualCanonicalizing(['rtc_code_catalog', 'fact_definitions', 'questions'])
        ->and(array_diff_assoc($new['catalog_versions'], $old['catalog_versions']))->toBe(['rtc_codes' => '2026.11.06-b34-draft1'])
        ->and($new['resource_paths']['rtc_transition_catalog'])->toBe($old['resource_paths']['rtc_transition_catalog'])
        ->and($new['production_controls']['path'])->not->toBe($old['production_controls']['path']);
});

it('adds exactly the A158 split policy and keeps every other code byte-identical', function (): void {
    $codes = b34Knowledge()['documents']['rtc_code_catalog'];
    $oldCodes = b34Knowledge(B34_PREVIOUS)['documents']['rtc_code_catalog'];
    expect(array_values(array_diff(array_keys($codes), array_keys($oldCodes))))->toBe([SplitPaymentIntegrationB34::KEY])
        ->and(array_diff(array_keys($oldCodes), array_keys($codes)))->toBe([])
        ->and($codes[SplitPaymentIntegrationB34::KEY])->toBe(SplitPaymentIntegrationB34::POLICY);
    foreach ($oldCodes as $key => $value) {
        if ($key !== 'version') {
            expect(json_encode($codes[$key], JSON_THROW_ON_ERROR))->toBe(json_encode($value, JSON_THROW_ON_ERROR));
        }
    }
});

it('adds the split ledger fact right after the A157 ledger, with a question of the same rank', function (): void {
    $new = b34Knowledge()['documents'];
    $old = b34Knowledge(B34_PREVIOUS)['documents'];
    $keys = array_column($new['fact_definitions'], 'key');
    $position = array_search('rtc.acquirer_collection_ledger', $keys, true);
    expect(count($keys))->toBe(count($old['fact_definitions']) + 1)
        ->and($new['fact_definitions'][$position + 1])->toBe(['key' => 'rtc.split_payment_ledger', 'type' => 'ARRAY', 'material' => true]);
    $questions = array_column($new['questions'], null, 'fact_key');
    expect($questions['rtc.split_payment_ledger']['rank'])->toBe($questions['rtc.acquirer_collection_ledger']['rank'])
        ->and($questions['rtc.split_payment_ledger']['answer_type'])->toBe('ARRAY')
        ->and(count($new['questions']))->toBe(count($old['questions']) + 1);
});

it('keeps the installed split route blocked: no official deployment is invented', function (): void {
    $result = b34Evaluate(false);
    $candidate = array_column($result['trace'], null, 'stage')['SYNTHETIC_ARITHMETIC156'];
    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and(b34Knowledge()['documents']['rtc_transition_catalog']['split_deployments'])->toBe([])
        ->and($candidate['candidate_reasons'])->toBe(['RTC_SPLIT_OFFICIAL_DEPLOYMENT_NOT_PROVEN']);
});

it('reconciles the split ledger on the installed bundle like the in-memory candidate once a deployment row exists', function (): void {
    $result = b34Evaluate(true);
    [$input, $knowledge] = splitB34Scenario();
    $reference = evaluate156($input, $knowledge);
    $split = fn (array $r): array => array_column($r['trace'], null, 'stage')['SYNTHETIC_ARITHMETIC156']['candidate_decision']['collection']['split_ledger158'];
    expect($result['reason_codes'])->toContain('RTC_SYNTHETIC_TEST_ONLY')
        ->and($split($result)['status'])->toBe('LOCAL_SPLIT_LEDGER_RECONCILED')
        ->and($split($result)['taxes'])->toBe($split($reference)['taxes']);
});

it('leaves the B33 bundle unaware of the split ledger: the fact is rejected as unknown', function (): void {
    expect(fn () => b34Evaluate(true, B34_PREVIOUS))->toThrow(RuntimeException::class, 'Unknown fact in scenario: rtc.split_payment_ledger');
});
