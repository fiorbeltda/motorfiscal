<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DocumentPriceFactProjection112;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;

dataset('material119-price-runners', function (): array {
    $rows = [];
    foreach (['material119-price-expectations.json', 'material119-price-upstream-expectations.json'] as $name) {
        $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/'.$name), true, 512, JSON_THROW_ON_ERROR);
        foreach ($manifest['cases'] as $case) {
            foreach (['rtc', 'integrated'] as $runner) {
                $key = $case['name'].'-'.$runner;
                if (isset($rows[$key])) {
                    throw new RuntimeException('Duplicate116 runner case:'.$key);
                }
                $rows[$key] = [$case, $runner];
            }
        }
    }

    return $rows;
});

it('binds119 material-input policy, geography and freshly qualified documentary prices without changing captured nominal values or authorizing documents', function (array $case, string $runner): void {
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.20-119-draft1.json');
    expect(hash_file('sha256', base_path($case['path'])))->toBe($case['scenario_sha256']);
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->load(base_path($case['path']));
    $before = CanonicalJson::hash($scenario->source);
    $result = $runner === 'rtc' ? (new RtcScenarioRunService($factory))->evaluate($knowledge, $scenario)->toArray()
        : $factory->makeFromKnowledge($knowledge)->evaluate($scenario->input)->toArray();
    expect($result['status'])->toBe($case['status'])->and($result['reason_codes'])->toContain($case['reason'])
        ->and(CanonicalJson::hash($scenario->source))->toBe($before);
    $rtc = $runner === 'rtc' ? $result : array_column($result['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
    $stages = array_column($rtc['trace'], null, 'stage');
    if (isset($stages['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION'])) {
        expect($stages['MATERIAL_INPUT_POLICY119']['status'])->toBe('RESOLVED')
            ->and($stages['MATERIAL_INPUT_POLICY119']['payment_proven'])->toBeFalse()
            ->and($stages['ADJUSTMENT_INPUT119']['status'])->toBe('RESOLVED');
        expect($stages['OBSERVED_GEOGRAPHIC_REFERENCE']['status'])->toBe('OBSERVED_GEOGRAPHIC_REFERENCE_CONSISTENT')
            ->and($stages['OBSERVED_GEOGRAPHIC_REFERENCE']['historical_validity_proven'])->toBeFalse()
            ->and($stages['OBSERVED_GEOGRAPHIC_REFERENCE']['fiscal_destination_determined'])->toBeFalse()
            ->and($stages['OBSERVED_GEOGRAPHIC_REFERENCE']['production_ready'])->toBeFalse();
    }
    if (($case['temporal_transition'] ?? null) !== null) {
        $transition = $case['temporal_transition'];
        expect($transition['old_status'])->toBe('LOCAL_ISS_DOCUMENT_PRICES_COMPOSED')
            ->and($result['reason_codes'])->toContain('PRICE111_COMPOSITION_EVIDENCE_REVOKED_OR_SUPERSEDED')
            ->and($transition['event_dates_rewritten'])->toBeFalse();
        foreach ($transition['preserved_events'] as $eventKey => $event) {
            expect(new DateTimeImmutable($event))->toBeGreaterThan(new DateTimeImmutable($transition['old_known_at']))
                ->toBeLessThanOrEqual(new DateTimeImmutable($transition['new_known_at']))
                ->and($scenario->source['facts'][DocumentPriceFactProjection112::COMPOSITION_PROOFS][0][$eventKey])->toBe($event);
        }
    }
    if (array_key_exists('captured108_nominal_stage', $case)) {
        expect($stages['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION'] ?? null)->toBe($case['captured108_nominal_stage'])
            ->and($stages['PARTIAL_EXPORT_FX_TRANSACTION'] ?? null)->toBe($case['captured108_fx_stage']);
    }
    expect($rtc['decision'])->toBe([])->and($stages)->not->toHaveKey('PARTIAL_EXPORT_DATED_DOCUMENTS')
        ->not->toHaveKey('PARTIAL_EXPORT_DPS_IDENTITY')->not->toHaveKey('PARTIAL_EXPORT_ISS_RESULTS')
        ->not->toHaveKey('PARTIAL_EXPORT_SP_RESULTS');
    if ($case['price_status'] === null) {
        expect($stages)->not->toHaveKey('PARTIAL_EXPORT_CONTRACT_PRICES')->not->toHaveKey('PARTIAL_EXPORT_ISS_PRICE_COMPOSITION');

        return;
    }
    expect($stages['PARTIAL_EXPORT_CONTRACT_PRICES']['status'])->toBe($case['price_status']);
    if (isset($case['expected_price_dated_reason'])) {
        expect($stages['PARTIAL_EXPORT_CONTRACT_PRICES']['dated_reason'])->toBe($case['expected_price_dated_reason']);
    }
    $context = $stages['PARTIAL_EXPORT_DOCUMENT_PRICE_CONTEXT'];
    expect($context['provided_context_facts_hash'])->toBe(CanonicalJson::hash($scenario->input->context->facts->canonicalValues()))
        ->and($context['monetary_record_fact_keys_bound'])->toBe([DocumentPriceFactProjection112::PRICE_RECORDS, DocumentPriceFactProjection112::COMPOSITION_RECORDS])
        ->and($context['excluded_price_proof_carrier_keys'])->toBe([DocumentPriceFactProjection112::PRICE_PROOFS, DocumentPriceFactProjection112::COMPOSITION_PROOFS]);
    $order = array_column($rtc['trace'], 'stage');
    expect(array_search('PARTIAL_EXPORT_DOCUMENT_PRICE_CONTEXT', $order, true))
        ->toBeGreaterThan(array_search('PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION', $order, true));
    if ($case['composition_status'] === null) {
        expect($stages)->not->toHaveKey('PARTIAL_EXPORT_ISS_PRICE_COMPOSITION');

        return;
    }
    $price = $stages['PARTIAL_EXPORT_CONTRACT_PRICES'];
    $composition = $stages['PARTIAL_EXPORT_ISS_PRICE_COMPOSITION'];
    expect($composition['status'])->toBe($case['composition_status']);
    foreach (['rtc_facts_rewritten', 'rtc_allocation_used_as_iss_price_rule', 'conditional_discount_subtracted_from_iss_price',
        'iss_base_calculated', 'per_document_tax_amount_calculated', 'documentary_currency_conversion_qualified',
        'payment_receipt_proven', 'legal_applicability_approved', 'government_evidence_authenticated',
        'xml_generated', 'posting_authorized', 'production_ready'] as $flag) {
        expect($composition[$flag])->toBeFalse();
    }
    if ($composition['status'] !== 'LOCAL_ISS_DOCUMENT_PRICES_COMPOSED') {
        return;
    }
    expect($price['current_facts_hash'])->toBe($context['qualification_context_facts_hash'])
        ->and($composition['price110_binding_hash'])->toBe($price['binding_hash'])
        ->and(array_column($composition['documents'], 'price_for_iss_evaluation_in_input_currency'))->toBe($case['expected_iss_prices'])
        ->and(array_column($composition['documents'], 'payment_candidate_if_conditional_terms_satisfied'))->toBe($case['expected_payment_candidates'])
        ->and(array_column($composition['documents'], 'iss_supply_on'))->toBe($case['expected_iss_dates']);
    if (($case['rtc_gross_currency'] ?? null) === 'USD') {
        expect($stages['PARTIAL_EXPORT_FX_TRANSACTION']['status'])->toBe('LOCAL_PARTIAL_EXPORT_FX_TRANSACTION_BOUND')
            ->and($price['rtc_price_reconciled'])->toBeFalse()
            ->and(array_search('PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION', $order, true))
            ->toBeGreaterThan(array_search('PARTIAL_EXPORT_FX_TRANSACTION', $order, true));
    }
    $control = $scenario->source;
    $control['facts']['rtc.assess_export_document_prices'] = false;
    $controlScenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($control);
    $controlResult = $runner === 'rtc' ? (new RtcScenarioRunService($factory))->evaluate($knowledge, $controlScenario)->toArray()
        : $factory->makeFromKnowledge($knowledge)->evaluate($controlScenario->input)->toArray();
    $controlRtc = $runner === 'rtc' ? $controlResult : array_column($controlResult['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
    $controlStages = array_column($controlRtc['trace'], null, 'stage');
    expect($controlStages)->not->toHaveKey('PARTIAL_EXPORT_DOCUMENT_PRICE_CONTEXT')
        ->and($stages['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION'])->toBe($controlStages['PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION']);
})->with('material119-price-runners');

it('retains both literal lifecycle events that became known instead of moving them to keep a positive outcome', function (): void {
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/material119-price-expectations.json'), true, 512, JSON_THROW_ON_ERROR);
    $transitions = array_values(array_filter($manifest['cases'], fn (array $case): bool => ($case['temporal_transition'] ?? null) !== null));
    expect($transitions)->toHaveCount(2);
    $events = [];
    foreach ($transitions as $case) {
        $events = [...$events, ...array_keys($case['temporal_transition']['preserved_events'])];
    }
    sort($events);
    expect($events)->toBe(['revoked_at', 'superseded_at']);
});
