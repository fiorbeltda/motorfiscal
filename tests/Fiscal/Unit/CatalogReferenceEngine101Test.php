<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Production\Phase4bReadinessAssessor;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

function catalog101EngineResult(string $route, string $mutation = '', ?string $knownAt = null, string $version = '101'): array
{
    $factory = new FiscalEngineFactory;
    $root = dirname(__DIR__, 3);
    $knowledge = $factory->captureKnowledge($root.'/resources/fiscal', 'bundles/consumption-phase4b-2026.10.16-'.$version.'-draft1.json');
    $manifest = json_decode((string) file_get_contents($root.'/tests/Fixtures/catalog101-scenario-expectations.json'), true, 512, JSON_THROW_ON_ERROR);
    $case = array_values(array_filter($manifest['cases'], fn (array $row): bool => $route === 'standard'
        ? $row['name'] === 'regular-saas-positive' : $row['name'] === 'export-brl-control'))[0];
    $source = json_decode((string) file_get_contents($root.'/'.$case['path']), true, 512, JSON_THROW_ON_ERROR);
    $source['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $source['catalog_versions'] = $knowledge['catalog_versions'];
    if ($knownAt !== null) {
        $source['knowledge_at'] = $knownAt;
    }
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $docs = $knowledge['documents'];
    $codes = $docs['rtc_code_catalog'];
    switch ($mutation) {
        case 'missing-policy': unset($codes['cclass_reference_policy']);
            break;
        case 'wrong-policy-type': $codes['cclass_reference_policy'] = false;
            break;
        case 'policy-deployed': $codes['cclass_reference_policy']['remote_deployment_proven'] = true;
            break;
        case 'source-version': $codes['sources']['cclasstrib']['version'] = 'IT 2025.002 v1.60';
            break;
        case 'source-hash': $codes['sources']['cclasstrib']['sha256'] = str_repeat('0', 64);
            break;
        case 'table-tamper': $codes['cclasstrib']['000001']['tpDoacao'] = 0;
            break;
        case 'cst-tamper': unset($codes['cst_reference_rows']['000']);
            break;
        case 'nfse-disabled':
            foreach ($codes['engine_decision_rules'] as &$rule) {
                $rule['cclasstrib'] = '410028';
            }
            unset($rule);
            break;
    }
    $engine = new ConsumptionReformEngine(new TemporalRateCatalog($docs['rtc_rate_catalog']), $codes, new TransitionCatalog($docs['rtc_transition_catalog']));

    return $engine->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
}

dataset('catalog101-engine-gates', function (): array {
    $data = [];
    foreach (['standard', 'partial'] as $route) {
        foreach (['missing-policy' => 'SOURCE_POLICY_NOT_BOUND', 'wrong-policy-type' => 'SOURCE_POLICY_NOT_BOUND',
            'policy-deployed' => 'SOURCE_POLICY_NOT_BOUND', 'source-version' => 'SOURCE_PROVENANCE_NOT_BOUND',
            'source-hash' => 'SOURCE_PROVENANCE_NOT_BOUND', 'table-tamper' => 'SOURCE_ROWS_CHANGED',
            'cst-tamper' => 'SOURCE_ROWS_CHANGED', 'nfse-disabled' => 'REFERENCE_NOT_PERMITTED_FOR_DOCUMENT_MODEL'] as $mutation => $reason) {
            $data[$route.'-'.$mutation] = [$route, $mutation, $reason];
        }
    }

    return $data;
});

it('stops both classified paths before calculation when the official reference is not bound', function (string $route, string $mutation, string $reason): void {
    $result = catalog101EngineResult($route, $mutation);
    expect($result['status'])->toBe($mutation === 'nfse-disabled' ? 'INVALID' : 'ACTION_REQUIRED')
        ->and($result['reason_codes'])->toContain('CCLASS100_'.$reason)->and($result['decision'])->toBe([]);
    $stages = array_column($result['trace'], null, 'stage');
    expect($stages)->toHaveKey('CCLASSTRIB_REFERENCE')->not->toHaveKey('PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION')
        ->and($stages['CCLASSTRIB_REFERENCE']['context'])->toBe($route === 'standard' ? 'STANDARD_OPERATION' : 'PARTIAL_EXPORT_DOMESTIC');
})->with('catalog101-engine-gates');

it('does not consume the source one microsecond before observation but accepts the exact instant', function (): void {
    $before = catalog101EngineResult('standard', '', '2026-10-16T16:56:31.622021+00:00');
    $at = catalog101EngineResult('standard', '', '2026-10-16T16:56:31.622022+00:00');
    expect($before['reason_codes'])->toContain('CCLASS100_SOURCE_NOT_YET_OBSERVED')
        ->and($before['decision'])->toBe([])->and($at['status'])->toBe('VALID');
});

it('does not retrofit the reference gate into captured legacy bundles', function (string $route): void {
    $result = catalog101EngineResult($route, '', null, '98');
    expect(array_column($result['trace'], 'stage'))->not->toContain('CCLASSTRIB_REFERENCE')
        ->and($result['status'])->toBe($route === 'standard' ? 'VALID' : 'ACTION_REQUIRED');
})->with(['standard', 'partial']);

it('counts current sources without granting production or classification approval', function (): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $report = (new Phase4bReadinessAssessor(new FiscalEngineFactory))->assess($root,
        'bundles/consumption-phase4b-2026.10.16-101-draft1.json', $root.'/production/phase4b-controls-2026.10.16-101-draft1.json');
    expect($report['ready'])->toBeFalse()->and($report['eligible_for_local_validation'])->toBeTrue()
        ->and($report['official_sources_checked'])->toBe(66)->and($report['official_source_hashes_valid'])->toBe(66)
        ->and($report['blockers'])->toHaveCount(122)->toContain('CCLASSTRIB_REFERENCE_LOCAL_ONLY_NOT_CLASSIFICATION_APPROVAL');
});
