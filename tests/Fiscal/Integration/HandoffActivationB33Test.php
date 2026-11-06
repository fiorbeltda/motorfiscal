<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\AcquirerCollection157;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

require_once dirname(__DIR__, 3).'/scripts/agent_a/collection157_fixture.php';
require_once dirname(__DIR__, 3).'/scripts/agent_a/domicile151_fixture.php';

// B33: installed bundle that activates A157 (acquirer collection) and withdraws the duplicated B25 settlement gate.
const B33_BUNDLE = 'bundles/consumption-phase4b-2026.11.06-b33-draft1.json';
const B33_PREVIOUS = 'bundles/consumption-phase4b-2026.11.05-b32-draft1.json';

/** @return array<string, mixed> */
function b33Manifest(string $path = B33_BUNDLE): array
{
    return json_decode((string) file_get_contents(resource_path('fiscal/'.$path)), true, 512, JSON_THROW_ON_ERROR);
}

/** @return array<string, mixed> */
function b33Knowledge(string $bundle = B33_BUNDLE): array
{
    return (new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'), $bundle);
}

/** A151 domicile report for the A157 scenario parties (required by the B27 gate since bundle B27). @return array<string, mixed> */
function b33DomicileReport(): array
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

/** A157 scenario evaluated against an installed bundle. @return array<string, mixed> */
function b33Evaluate(array $scenario, string $bundle = B33_BUNDLE): array
{
    $knowledge = b33Knowledge($bundle);
    $scenario['facts']['rtc.destination_domicile_report'] = [b33DomicileReport()];
    $scenario['knowledge_at'] = max($scenario['knowledge_at'], b33Manifest($bundle)['knowledge_available_at']);
    $ledger = $scenario['facts']['rtc.acquirer_collection_ledger'][0] ?? null;
    if (is_array($ledger)) {
        $ledger['known_at'] = $scenario['knowledge_at'];
        $scenario['facts']['rtc.acquirer_collection_ledger'] = [seal157($ledger)];
    }

    return evaluate156($scenario, $knowledge);
}

it('forks bundle B32 changing only the code catalog, facts, questions and production controls', function (): void {
    $new = b33Manifest();
    $old = b33Manifest(B33_PREVIOUS);
    expect($new['predecessor_bundle_id'])->toBe($old['bundle_id'])
        ->and(array_keys(array_diff_assoc($new['content_hashes'], $old['content_hashes'])))
        ->toEqualCanonicalizing(['rtc_code_catalog', 'fact_definitions', 'questions'])
        ->and(array_diff_assoc($new['catalog_versions'], $old['catalog_versions']))->toBe(['rtc_codes' => '2026.11.06-b33-draft1'])
        ->and($new['production_controls']['path'])->not->toBe($old['production_controls']['path']);
});

it('withdraws the B25 settlement gate and adds exactly the A157 policy, keeping every other code', function (): void {
    $codes = b33Knowledge()['documents']['rtc_code_catalog'];
    $oldCodes = b33Knowledge(B33_PREVIOUS)['documents']['rtc_code_catalog'];
    expect($oldCodes)->toHaveKey('settlement_cycle_b25')
        ->and($codes)->not->toHaveKey('settlement_cycle_b25')
        ->and(array_values(array_diff(array_keys($codes), array_keys($oldCodes))))->toBe([AcquirerCollection157::KEY])
        ->and($codes[AcquirerCollection157::KEY])->toBe(AcquirerCollection157::POLICY);
    foreach ($oldCodes as $key => $value) {
        if (! in_array($key, ['version', 'settlement_cycle_b25'], true)) {
            expect(json_encode($codes[$key], JSON_THROW_ON_ERROR))->toBe(json_encode($value, JSON_THROW_ON_ERROR));
        }
    }
});

it('replaces the settlement fact and question by the A157 ledger at the same position and rank', function (): void {
    $new = b33Knowledge()['documents'];
    $old = b33Knowledge(B33_PREVIOUS)['documents'];
    $newFacts = array_column($new['fact_definitions'], 'key');
    $oldFacts = array_column($old['fact_definitions'], 'key');
    $position = array_search('rtc.settlement_records', $oldFacts, true);
    expect($newFacts)->not->toContain('rtc.settlement_records')
        ->and(count($newFacts))->toBe(count($oldFacts))
        ->and($newFacts[$position])->toBe('rtc.acquirer_collection_ledger')
        ->and($new['fact_definitions'][$position])->toBe(['key' => 'rtc.acquirer_collection_ledger', 'type' => 'ARRAY', 'material' => true]);
    $newQuestions = array_column($new['questions'], null, 'fact_key');
    $oldQuestions = array_column($old['questions'], null, 'fact_key');
    expect($newQuestions)->not->toHaveKey('rtc.settlement_records')
        ->and($newQuestions['rtc.acquirer_collection_ledger']['rank'])->toBe($oldQuestions['rtc.settlement_records']['rank'])
        ->and($newQuestions['rtc.acquirer_collection_ledger']['answer_type'])->toBe('ARRAY');
});

it('reconciles the acquirer collection through A157 on the installed bundle, like the in-memory candidate', function (): void {
    [$input] = collection157Scenario();
    $result = b33Evaluate($input);
    [$candidateInput, $candidateKnowledge] = collection157Scenario();
    $candidate = evaluate156($candidateInput, $candidateKnowledge);
    // Synthetic ledgers withhold the final decision (A156 rule); the reconciliation lives in the candidate decision.
    $reconciliation = fn (array $r): array => array_column($r['trace'], null, 'stage')['SYNTHETIC_ARITHMETIC156']['candidate_decision']['collection']['reconciliation157'];
    $stages = array_column($result['trace'], 'stage');
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason_codes'])->toContain('RTC_SYNTHETIC_TEST_ONLY')
        ->and($stages)->not->toContain('SETTLEMENT_CYCLE_B25')
        ->and($reconciliation($result)['taxes'])->toBe($reconciliation($candidate)['taxes'])
        ->and($reconciliation($result)['taxes']['IBS']['pending_transfer_to_supplier'])->toBe('0.20')
        ->and($reconciliation($result)['taxes']['CBS']['remaining'])->toBe('1.00');
});

it('requires the A157 ledger on the acquirer route and never reads settlement records', function (): void {
    [$input] = collection157Scenario();
    unset($input['facts']['rtc.acquirer_collection_ledger']);
    $result = b33Evaluate($input);
    expect($result['reason_codes'])->toContain('RTC_ACQUIRER_COLLECTION_LEDGER_REQUIRED')
        ->and($result['missing_facts'] ?? [])->not->toContain('rtc.settlement_records');
});
