<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Production\Phase4bReadinessAssessor;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

function identity104Engine(array $facts = [], string $mutation = '', array $remove = []): array
{
    $root = dirname(__DIR__, 3);
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root.'/resources/fiscal', 'bundles/consumption-phase4b-2026.10.19-104-draft1.json');
    $source = json_decode((string) file_get_contents($root.'/resources/fiscal/scenarios/dps-identity-2026.10.19-104-draft1/local-pair.json'), true, 512, JSON_THROW_ON_ERROR);
    $source['facts'] = array_replace($source['facts'], $facts);
    foreach ($remove as $key) {
        unset($source['facts'][$key]);
    }
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $docs = $knowledge['documents'];
    $codes = $docs['rtc_code_catalog'];
    switch ($mutation) {
        case 'identity-policy-missing': unset($codes['partial_export_dps_identity_policy']);
            break;
        case 'identity-policy-type': $codes['partial_export_dps_identity_policy'] = false;
            break;
        case 'identity-policy-source': $codes['partial_export_dps_identity_policy']['sources']['municipal_manual'] = str_repeat('0', 64);
            break;
        case 'identity-policy-deployment': $codes['partial_export_dps_identity_policy']['remote_deployment_proven'] = true;
            break;
        case 'iss-policy-missing': unset($codes['partial_export_iss_result_policy']);
            break;
        case 'sp-policy-missing': unset($codes['partial_export_sp_result_policy']);
            break;
        case 'reference-policy-missing': unset($codes['cclass_reference_policy']);
            break;
    }
    $engine = new ConsumptionReformEngine(new TemporalRateCatalog($docs['rtc_rate_catalog']), $codes, new TransitionCatalog($docs['rtc_transition_catalog']));

    return $engine->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
}

it('requires both prior ISS assessments rather than bypassing them with the identity flag', function (array $facts, array $missing): void {
    $result = identity104Engine($facts);
    expect($result['reason_codes'])->toBe(['RTC_EXPORT_DPS104_PRIOR_ISS_ASSESSMENTS_REQUIRED'])
        ->and($result['missing_facts'])->toBe($missing)->and(array_column($result['trace'], 'stage'))->not->toContain('PARTIAL_EXPORT_DPS_IDENTITY');
})->with([
    [['rtc.assess_export_iss_results' => false], ['rtc.assess_export_iss_results']],
    [['rtc.assess_export_sp_results' => false], ['rtc.assess_export_sp_results']],
    [['rtc.assess_export_iss_results' => false, 'rtc.assess_export_sp_results' => false], ['rtc.assess_export_iss_results', 'rtc.assess_export_sp_results']],
]);

it('never evaluates identity when an earlier source or municipal policy gate fails', function (string $mutation, string $reason): void {
    $result = identity104Engine([], $mutation);
    expect($result['reason_codes'])->toContain($reason)->and($result['decision'])->toBe([])
        ->and(array_column($result['trace'], 'stage'))->not->toContain('PARTIAL_EXPORT_DPS_IDENTITY');
})->with([
    ['iss-policy-missing', 'RTC_EXPORT_ISS96_POLICY_NOT_BOUND'],
    ['sp-policy-missing', 'RTC_EXPORT_SP98_POLICY_NOT_BOUND'],
    ['reference-policy-missing', 'CCLASS100_SOURCE_POLICY_NOT_BOUND'],
]);

it('refuses missing,malformed or escalated104 source policies', function (string $mutation): void {
    $result = identity104Engine([], $mutation);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason_codes'])->toBe(['DPS103_SOURCE_POLICY_NOT_BOUND']);
})->with(['identity-policy-missing', 'identity-policy-type', 'identity-policy-source', 'identity-policy-deployment']);

it('requests identity records explicitly only after valid prior gates', function (): void {
    $result = identity104Engine([], '', ['rtc.export_dps_identity_records']);
    expect($result['reason_codes'])->toBe(['RTC_EXPORT_DPS104_IDENTITY_RECORDS_REQUIRED'])
        ->and($result['missing_facts'])->toBe(['rtc.export_dps_identity_records']);
});

it('leaves the opt-out path unchanged and does not consume unused records or policy', function (): void {
    $result = identity104Engine(['rtc.assess_export_dps_identity' => false], 'identity-policy-missing');
    expect($result['reason_codes'])->toBe(['RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN'])
        ->and(array_column($result['trace'], 'stage'))->not->toContain('PARTIAL_EXPORT_DPS_IDENTITY');
});

it('does not evaluate the identity component for another route', function (): void {
    $result = identity104Engine(['rtc.market' => 'DOMESTIC']);
    expect($result['reason_codes'])->toContain('RTC_EXPORT_ISS96_PARTIAL_EXPORT_ROUTE_REQUIRED')
        ->and(array_column($result['trace'], 'stage'))->not->toContain('PARTIAL_EXPORT_DPS_IDENTITY');
});

it('blocks production independently of a locally composed pair of DPS identifiers', function (): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $report = (new Phase4bReadinessAssessor(new FiscalEngineFactory))->assess($root,
        'bundles/consumption-phase4b-2026.10.19-104-draft1.json', $root.'/production/phase4b-controls-2026.10.19-104-draft1.json');
    expect($report['ready'])->toBeFalse()->and($report['eligible_for_local_validation'])->toBeTrue()
        ->and($report['official_source_hashes_valid'])->toBe(71)->and($report['blockers'])->toHaveCount(123)
        ->toContain('DPS_IDENTITY_LOCAL_MAPPING_ONLY_NOT_ISSUANCE_APPROVAL');
});
