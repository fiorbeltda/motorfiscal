<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use App\Modules\Fiscal\Infrastructure\Schema\DereXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

it('integrates qualified art195 yields once and reconciles gross movements to the monthly books', function (string $case): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $read = static fn (string $p): array => json_decode(file_get_contents($root.$p), true, 512, JSON_THROW_ON_ERROR);
    $k = (new FiscalEngineFactory)->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.06-27.json');
    $factory = new FiscalEngineFactory;
    $folder = 'scenarios/dere-variable-yield-2026.10.06-27/';
    $v = $read($folder.$case.'.json');
    $engineCase = str_starts_with($case, 'engine-');
    $input = $engineCase ? $v['scenario'] : $read($folder.$v['financial_scenario']);
    $scenario = $factory->scenarioLoaderFromKnowledge($k)->fromArray($input);
    $result = (new RtcScenarioRunService($factory))->evaluate($k, $scenario)->toArray();
    if ($engineCase) {
        expect($result['reason_codes'])->toBe([$v['expectation']])->and($result['decision'])->toBe([]);

        return;
    }
    expect($result['reason_codes'])->toBe(['RTC_FINANCIAL_DOCUMENT_PENDING']);
    $op = array_column($result['trace'], null, 'stage')['FINANCIAL_OPERATION_AND_BASE'];
    $c = $k['documents']['rtc_code_catalog'];
    $r = (new DereMonthlyClosingXmlProjector)->project($op, $v, $scenario->input->context->knowledgeAt,
        ['closing' => $c['dere_monthly_closing_projection'], 'balance' => $c['dere_trial_balance_projection'],
            'deductions' => $c['dere_deduction_projection'], 'pgcc_projection' => $c['dere_pgcc_projection'],
            'contributor_projection' => $c['dere_contributor_projection']]);
    if ($v['corpus_expectation']['status'] !== 'PROJECTED') {
        expect($r['reason'])->toBe($v['corpus_expectation']['reason'])->and($r)->not->toHaveKey('xml');

        return;
    }
    $debt = $r['debt_yield_projection'];
    expect($r['status'])->toBe('PROJECTED')->and($op['base']['net_base'])->toBe($v['corpus_expectation']['base'])
        ->and($debt['total_vApur'])->toBe($v['corpus_expectation']['auxiliary_base'])->and($r['period_closed'])->toBeFalse()
        ->and($debt['production_ready'])->toBeFalse();
    foreach ($debt['qualified_entries'] as $entry) {
        expect($entry['book_apur_amount'])->toBe('0.00')->and($entry['tax_origin'])->toBe('D2101')
            ->and($entry['calculation']['rounding_contract'])->toBe('DERE_HALF_EVEN_8_2')
            ->and($entry['position_mode'])->toBe('DAILY_MEMORY')->and($entry['calculation']['daily_memory'])->toHaveCount(30)
            ->and($entry['normative_method_homologated'])->toBeFalse()
            ->and($entry['taxable_amount'])->toBe($entry['calculation']['monthly']['vApur']);
    }
    $schema = $c['dere_monthly_closing_projection']['debt_yield_projection']['xsd'];
    $validation = (new DereXmlSchemaValidator)->validate($debt['xml'], $root.'sources/official/'.$schema['source_file'], $schema['source_sha256'],
        $debt['schema'], $root.'sources/official/'.$schema['signature_source_file'], $schema['signature_source_sha256']);
    expect($validation['reason'])->toBe('RTC_DERE_UNSIGNED_LOCAL_CANDIDATE')->and($validation['errors'])->toHaveCount(1);
})->with(function (): array {
    $cases = [];
    foreach (glob(dirname(__DIR__, 3).'/resources/fiscal/scenarios/dere-variable-yield-2026.10.06-27/*.json') as $path) {
        $v = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (isset($v['corpus_expectation']) || isset($v['expectation'])) {
            $cases[] = basename($path, '.json');
        }
    }

    return $cases;
});
