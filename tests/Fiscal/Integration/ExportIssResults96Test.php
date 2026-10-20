<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;

dataset('export-iss96-cases-runners', function (): array {
    $names = ['usd-direct', 'usd-weekend', 'usd-quarter', 'usd-mostly-foreign', 'usd-mostly-domestic', 'brl-control',
        'no-operation-reference', 'no-contexts', 'duplicate-role', 'wrong-operation-proof', 'swapped-document-proof',
        'proof-hash', 'payment-not-result', 'mixed-result', 'foreign-rtc-iss-br', 'domestic-rtc-iss-abroad',
        'wrong-route', 'full-export-not-partial', 'advance-combination', 'disabled-malformed-contexts', 'future-cbs-not-guessed'];
    $data = [];
    foreach ($names as $name) {
        foreach (['rtc', 'integrated'] as $runner) {
            $data[$name.'-'.$runner] = [$name, $runner];
        }
    }

    return $data;
});

it('binds ISS results only after native partial-export gates and leaves documents pending', function (string $name, string $runner): void {
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.16-96-draft1.json');
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->load(resource_path('fiscal/scenarios/export-iss-2026.10.16-96-draft1/'.$name.'.json'));
    $evaluate = fn () => $runner === 'rtc'
        ? (new RtcScenarioRunService($factory))->evaluate($knowledge, $scenario)->toArray()
        : $factory->makeFromKnowledge($knowledge)->evaluate($scenario->input)->toArray();
    $result = $evaluate();
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result)->toBe($evaluate());
    $reasons = ['no-operation-reference' => 'OPERATION_DOCUMENT_PARTIES_REQUIRED', 'no-contexts' => 'TWO_INDEPENDENT_RESULT_CONTEXTS_REQUIRED',
        'duplicate-role' => 'DUPLICATE_RESULT_ROLE', 'wrong-operation-proof' => 'RESULT_CONTEXT_SOURCE_BINDING_CONFLICT',
        'swapped-document-proof' => 'RESULT_CONTEXT_SOURCE_BINDING_CONFLICT', 'proof-hash' => 'INDEPENDENT_ISS_RESULT_NOT_BOUND',
        'payment-not-result' => 'INDEPENDENT_ISS_RESULT_NOT_BOUND', 'mixed-result' => 'INDEPENDENT_ISS_RESULT_NOT_BOUND',
        'wrong-route' => 'PARTIAL_EXPORT_ROUTE_REQUIRED', 'full-export-not-partial' => 'PARTIAL_EXPORT_ROUTE_REQUIRED',
        'advance-combination' => 'SEPARATE_TEMPORAL_CONTEXT_REQUIRED'];
    $expected = $name === 'future-cbs-not-guessed' ? 'RTC_PARTIAL_EXPORT_DOMESTIC_CLASSIFICATION_NOT_PROVEN'
        : (isset($reasons[$name]) ? 'RTC_EXPORT_ISS96_'.$reasons[$name] : 'RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN');
    expect($result['reason_codes'])->toContain($expected);
    $rtc = $result;
    if ($runner === 'integrated') {
        $trace = array_column($result['trace'], 'payload', 'type');
        $rtc = $trace['CONSUMPTION_REFORM'];
        $tax = in_array($name, ['brl-control', 'future-cbs-not-guessed'], true) ? '20.00' : ($name === 'usd-weekend' ? '103.98' : '103.59');
        expect($trace['ISS_CALCULATION']['tax_amount'])->toBe($tax)
            ->and($trace['ISS_CALCULATION']['gross_amount'])->toBe($scenario->source['facts']['operation.amount']);
    }
    $stages = array_column($rtc['trace'], null, 'stage');
    $bound = in_array($name, ['usd-direct', 'usd-weekend', 'usd-quarter', 'usd-mostly-foreign', 'usd-mostly-domestic', 'brl-control',
        'foreign-rtc-iss-br', 'domestic-rtc-iss-abroad'], true);
    if ($bound) {
        $local = $stages['PARTIAL_EXPORT_ISS_RESULTS'];
        expect($local['status'])->toBe('LOCAL_CURRENT_EXPORT_ISS_RESULTS_BOUND')
            ->and(array_column($local['documents'], 'role'))->toBe(['RTC_FOREIGN', 'RTC_DOMESTIC'])
            ->and($local['archive_snapshot_replayed'])->toBeFalse();
        $scopes = $name === 'foreign-rtc-iss-br' ? ['BR', 'BR'] : ($name === 'domestic-rtc-iss-abroad' ? ['ABROAD', 'ABROAD'] : ['ABROAD', 'BR']);
        expect(array_column(array_column($local['documents'], 'iss_result'), 'result_scope'))->toBe($scopes);
        foreach (['economic_operation_identity_authenticated', 'fiscal_document_identifiers_qualified',
            'iss_supply_date_materially_qualified', 'documentary_currency_conversion_qualified', 'per_document_tax_amount_calculated',
            'full_operation_iss_rewritten', 'source_effective_date_proven', 'legal_applicability_approved',
            'xml_generated', 'document_obligation_complete', 'posting_authorized', 'production_ready'] as $flag) {
            expect($local[$flag])->toBeFalse();
        }
        $order = array_column($rtc['trace'], 'stage');
        expect(array_search('PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION', $order, true))
            ->toBeLessThan(array_search('PARTIAL_EXPORT_ISS_RESULTS', $order, true));
    } elseif (in_array($name, ['wrong-route', 'full-export-not-partial', 'advance-combination', 'disabled-malformed-contexts', 'future-cbs-not-guessed'], true)) {
        expect($stages)->not->toHaveKey('PARTIAL_EXPORT_ISS_RESULTS');
    } else {
        expect($stages['PARTIAL_EXPORT_ISS_RESULTS']['status'])->toBe('ACTION_REQUIRED');
    }
    expect($result['decision'])->toBe([]);
})->with('export-iss96-cases-runners');
