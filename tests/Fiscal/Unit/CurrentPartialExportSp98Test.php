<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\CurrentPartialExportSp98;
use App\Modules\Fiscal\Infrastructure\Production\Phase4bReadinessAssessor;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

function sp98Fixture(): array
{
    $root = dirname(__DIR__, 3);
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root.'/resources/fiscal', 'bundles/consumption-phase4b-2026.10.16-98-draft1.json');
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->load($root.'/resources/fiscal/scenarios/export-sp-2026.10.16-98-draft1/usd-direct.json');
    $input = $scenario->input->context;

    return [$input->facts->canonicalValues(), $input->competenceDate, $input->knowledgeAt,
        $knowledge['documents']['rtc_code_catalog']['partial_export_iss_result_policy'],
        $knowledge['documents']['rtc_code_catalog']['partial_export_sp_result_policy']];
}

it('requires every municipal98 policy field instead of treating the opt-in as legal approval', function (string $field): void {
    [$facts, $on, $knownAt, $national, $policy] = sp98Fixture();
    unset($policy[$field]);
    $result = (new CurrentPartialExportSp98)->qualify($facts, $on, $knownAt, $national, $policy);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['production_ready'])->toBeFalse()
        ->and($result['municipal_documentary_conditions_locally_bound'] ?? false)->toBeFalse();
})->with(['current_binding_contract', 'source_effective_date_proven', 'contract', 'source_sha256', 'source_observed_at', 'scope']);

it('binds the documentary municipality to the current provider,not a recipient or another city', function (mixed $municipality): void {
    [$facts, $on, $knownAt, $national, $policy] = sp98Fixture();
    $facts['provider.municipality_ibge'] = $municipality;
    $result = (new CurrentPartialExportSp98)->qualify($facts, $on, $knownAt, $national, $policy);
    expect($result['reason'])->toBe('RTC_EXPORT_SP98_SP_SUPPLIER_ESTABLISHMENT_REQUIRED');
})->with(['3304557', null]);

it('refuses a municipal wrapper without valid national context binding', function (): void {
    [$facts, $on, $knownAt, $national, $policy] = sp98Fixture();
    unset($facts['rtc.export_iss_result_contexts']);
    expect((new CurrentPartialExportSp98)->qualify($facts, $on, $knownAt, $national, $policy)['reason'])
        ->toBe('RTC_EXPORT_SP98_CURRENT_NATIONAL_RESULTS_NOT_BOUND');
});

it('keeps semantic bindings stable while retaining raw order differences', function (): void {
    [$facts, $on, $knownAt, $national, $policy] = sp98Fixture();
    $component = new CurrentPartialExportSp98;
    $first = $component->qualify($facts, $on, $knownAt, $national, $policy);
    $facts['rtc.export_sp_result_proofs'] = array_reverse($facts['rtc.export_sp_result_proofs']);
    $second = $component->qualify($facts, $on, $knownAt, $national, $policy);
    expect($first['documents'])->toBe($second['documents'])->and($first['binding_hash'])->toBe($second['binding_hash'])
        ->and($first['current_facts_hash'])->not->toBe($second['current_facts_hash']);
});

it('adds a municipal production blocker without changing historical96 blockers', function (): void {
    $resource = dirname(__DIR__, 3).'/resources/fiscal';
    $report = (new Phase4bReadinessAssessor(new FiscalEngineFactory))->assess($resource,
        'bundles/consumption-phase4b-2026.10.16-98-draft1.json', $resource.'/production/phase4b-controls-2026.10.16-98-draft1.json');
    expect($report['ready'])->toBeFalse()->and($report['eligible_for_local_validation'])->toBeTrue()
        ->and($report['official_source_hashes_valid'])->toBe(62)
        ->and($report['blockers'])->toContain('SP_SOFTWARE_EXPORT_RESULT_LOCAL_CANDIDATE_ONLY')->toHaveCount(121);
});
