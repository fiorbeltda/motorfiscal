<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\CurrentPartialExportIss96;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\Infrastructure\Production\Phase4bReadinessAssessor;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

function exportIss96Fixture(): array
{
    $root = dirname(__DIR__, 3);
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root.'/resources/fiscal', 'bundles/consumption-phase4b-2026.10.16-96-draft1.json');
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->load($root.'/resources/fiscal/scenarios/export-iss-2026.10.16-96-draft1/usd-direct.json');

    return [$scenario, $knowledge];
}

it('requires each own96 policy binding instead of treating flags as legal approval', function (string $field): void {
    [$scenario, $knowledge] = exportIss96Fixture();
    $policy = $knowledge['documents']['rtc_code_catalog']['partial_export_iss_result_policy'];
    unset($policy[$field]);
    $result = (new CurrentPartialExportIss96)->qualify($scenario->input->context->facts->canonicalValues(),
        $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt, $policy);
    expect($result['reason'])->toBe('RTC_EXPORT_ISS96_POLICY_NOT_BOUND')->and($result['production_ready'])->toBeFalse();
})->with(['current_binding_contract', 'contract', 'source_sha256', 'source_observed_at', 'scope', 'source_effective_date_proven']);

it('keeps a stable semantic binding while preserving the different raw input ordering hash', function (): void {
    [$scenario, $knowledge] = exportIss96Fixture();
    $facts = $scenario->input->context->facts->canonicalValues();
    $policy = $knowledge['documents']['rtc_code_catalog']['partial_export_iss_result_policy'];
    $component = new CurrentPartialExportIss96;
    $first = $component->qualify($facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt, $policy);
    $facts['rtc.export_iss_result_contexts'] = array_reverse($facts['rtc.export_iss_result_contexts']);
    $second = $component->qualify($facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt, $policy);
    expect($first['documents'])->toBe($second['documents'])->and($first['binding_hash'])->toBe($second['binding_hash'])
        ->and($first['current_facts_hash'])->not->toBe($second['current_facts_hash']);
});

it('refuses an active96 request when the code catalog has no96 policy', function (): void {
    [$scenario, $knowledge] = exportIss96Fixture();
    $docs = $knowledge['documents'];
    $codes = $docs['rtc_code_catalog'];
    unset($codes['partial_export_iss_result_policy']);
    $engine = new ConsumptionReformEngine(new TemporalRateCatalog($docs['rtc_rate_catalog']), $codes, new TransitionCatalog($docs['rtc_transition_catalog']));
    $result = $engine->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
    expect($result['reason_codes'])->toBe(['RTC_EXPORT_ISS96_POLICY_NOT_BOUND']);
});

it('does not expand the local period or turn source observation into legal effect', function (): void {
    [$scenario, $knowledge] = exportIss96Fixture();
    $policy = $knowledge['documents']['rtc_code_catalog']['partial_export_iss_result_policy'];
    $component = new CurrentPartialExportIss96;
    $facts = $scenario->input->context->facts->canonicalValues();
    expect($component->qualify($facts, LocalDate::fromString('2033-01-01'), new DateTimeImmutable('2033-01-02T12:00:00+00:00'), $policy)['reason'])
        ->toBe('RTC_EXPORT_ISS96_LOCAL_PERIOD_NOT_COVERED');
    $policy['source_effective_date_proven'] = true;
    expect($component->qualify($facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt, $policy)['reason'])
        ->toBe('RTC_EXPORT_ISS96_POLICY_NOT_BOUND');
});

it('keeps future CBS unavailable without extending the earlier classification just to reach its gate', function (): void {
    [, $knowledge] = exportIss96Fixture();
    $rate = (new TemporalRateCatalog($knowledge['documents']['rtc_rate_catalog']))->resolve('CBS', 'BR',
        LocalDate::fromString('2027-01-01'), new DateTimeImmutable('2027-01-02T12:00:00+00:00'));
    expect($rate['status'])->toBe('NOT_FOUND')->and($rate['reason'])->toBe('RTC_RATE_NOT_PROVEN:CBS');
});

it('adds an explicit production blocker while leaving the local candidate verifiable', function (): void {
    $resource = dirname(__DIR__, 3).'/resources/fiscal';
    $report = (new Phase4bReadinessAssessor(new FiscalEngineFactory))->assess($resource,
        'bundles/consumption-phase4b-2026.10.16-96-draft1.json', $resource.'/production/phase4b-controls-2026.10.16-96-draft1.json');
    expect($report['ready'])->toBeFalse()->and($report['eligible_for_local_validation'])->toBeTrue()
        ->and($report['official_source_hashes_valid'])->toBe(60)
        ->and($report['blockers'])->toContain('ISS_EXPORT_RESULT_LOCAL_CANDIDATE_ONLY')->toHaveCount(120);
});
