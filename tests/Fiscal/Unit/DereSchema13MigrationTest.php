<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

it('migrates monthly originals and preserves account-scoped continuation history', function (string $case): void {
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $read = static fn (string $path): array => json_decode(file_get_contents($root.$path), true, 512, JSON_THROW_ON_ERROR);
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.06-22.json');
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($read('scenarios/dere-schema13-2026.10.06-22/financial-scenario.json'));
    $result = (new RtcScenarioRunService($factory))->evaluate($knowledge, $scenario)->toArray();
    $operation = array_column($result['trace'], null, 'stage')['FINANCIAL_OPERATION_AND_BASE'];
    $codes = $knowledge['documents']['rtc_code_catalog'];
    $month = $read('scenarios/dere-schema13-2026.10.06-22/'.$case.'.json');
    $r = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $scenario->input->context->knowledgeAt,
        ['closing' => $codes['dere_monthly_closing_projection'], 'balance' => $codes['dere_trial_balance_projection'],
            'deductions' => $codes['dere_deduction_projection'], 'pgcc_projection' => $codes['dere_pgcc_projection'],
            'contributor_projection' => $codes['dere_contributor_projection']]);
    if ($month['corpus_expectation']['status'] !== 'PROJECTED') {
        expect($r['reason'])->toBe($month['corpus_expectation']['reason'])->and($r)->not->toHaveKey('xml');

        return;
    }
    expect($r['status'])->toBe('PROJECTED')->and($r['pgcc_projection']['schema'])->toBe('evtPGCC-v1_0_4.xsd')
        ->and($r['deductions']['schema'])->toBe('evtRelDeducoes-v0_0_2.xsd');
    $xml = new DOMDocument;
    $xml->loadXML($r['deductions']['xml']);
    expect($xml->getElementsByTagName('desdobDeducao')->item(0)->getAttribute('tpAtiv'))->toBe('01')
        ->and($xml->getElementsByTagName('cCta')->item(0)->textContent)->toBe('correspondent000')
        ->and($r['production_ready'])->toBeFalse();
})->with(['deduction-qualified', 'deduction-qualified-continuation', 'debt-qualified-both-codes',
    'missing-previous-account', 'wrong-previous-account', 'tampered-previous-account']);
