<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use App\Modules\Fiscal\Infrastructure\Schema\DereXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

it('reconciles principal-only D2101 titles and blocks unsupported or unproven movements', function (string $case): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $read = static fn (string $path): array => json_decode(file_get_contents($root.$path), true, 512, JSON_THROW_ON_ERROR);
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.06-23.json');
    $input = $read('scenarios/dere-principal-movement-2026.10.06-23/financial-scenario.json');
    $month = $read('scenarios/dere-principal-movement-2026.10.06-23/'.$case.'.json');
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input);
    $result = (new RtcScenarioRunService($factory))->evaluate($knowledge, $scenario)->toArray();
    $operation = array_column($result['trace'], null, 'stage')['FINANCIAL_OPERATION_AND_BASE'];
    $codes = $knowledge['documents']['rtc_code_catalog'];
    $r = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $scenario->input->context->knowledgeAt,
        ['closing' => $codes['dere_monthly_closing_projection'], 'balance' => $codes['dere_trial_balance_projection'],
            'deductions' => $codes['dere_deduction_projection'], 'pgcc_projection' => $codes['dere_pgcc_projection'],
            'contributor_projection' => $codes['dere_contributor_projection']]);
    if ($month['corpus_expectation']['status'] !== 'PROJECTED') {
        expect($r['reason'])->toBe($month['corpus_expectation']['reason'])->and($r)->not->toHaveKey('xml');

        return;
    }
    $debt = $r['debt_principal_movement_projection'];
    expect($r['status'])->toBe('PROJECTED')->and($debt['total_vApur'])->toBe('0.00')
        ->and($debt['title_count'])->toBe(count($month['debt_principal_movement']['titles']))
        ->and($debt['selic_calculation_performed'])->toBeFalse()->and($debt['tax_totalization_performed'])->toBeFalse()
        ->and($r['period_closed'])->toBeFalse()->and($operation['base']['net_base'])->toBe('700.00');
    $schema = $codes['dere_monthly_closing_projection']['debt_principal_movement_projection']['xsd'];
    $validation = (new DereXmlSchemaValidator)->validate($debt['xml'], $root.'sources/official/'.$schema['source_file'],
        $schema['source_sha256'], $debt['schema'], $root.'sources/official/'.$schema['signature_source_file'], $schema['signature_source_sha256']);
    expect($validation['reason'])->toBe('RTC_DERE_UNSIGNED_LOCAL_CANDIDATE')->and($validation['errors'])->toHaveCount(1);
})->with(function (): array {
    $cases = [];
    foreach (glob(dirname(__DIR__, 3).'/resources/fiscal/scenarios/dere-principal-movement-2026.10.06-23/*.json') as $path) {
        if (basename($path) !== 'financial-scenario.json') {
            $cases[] = basename($path, '.json');
        }
    }

    return $cases;
});
