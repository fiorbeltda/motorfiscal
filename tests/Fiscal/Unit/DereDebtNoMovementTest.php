<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DerePgccQualifier;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use App\Modules\Fiscal\Infrastructure\Schema\DereXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DereDebtNoMovementXmlProjector;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;
use App\Modules\Fiscal\NFSe\DereTrialBalanceXmlProjector;

/** @return array<string, mixed> */
function dereDebtNoMovementFixture(string $case = 'qualified-both-codes'): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $read = static fn (string $name): array => json_decode(file_get_contents($root.$name), true, 512, JSON_THROW_ON_ERROR);
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.06-21.json');
    $input = $read('scenarios/dere-d2101-no-movement-2026.10.06-21/financial-scenario.json');
    $month = $read('scenarios/dere-d2101-no-movement-2026.10.06-21/'.$case.'.json');
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input);
    $result = (new RtcScenarioRunService($factory))->evaluate($knowledge, $scenario)->toArray();
    $operation = array_column($result['trace'], null, 'stage')['FINANCIAL_OPERATION_AND_BASE'];
    $codes = $knowledge['documents']['rtc_code_catalog'];
    $rule = $codes['dere_monthly_closing_projection'];
    $known = $scenario->input->context->knowledgeAt;
    $pgcc = (new DerePgccQualifier)->resolve($month['pgcc'], $rule['pgcc_catalogs']['reference'], $rule['pgcc_catalogs']['tax_codes'],
        $known, false, $rule['pgcc_qualification_policy']);
    $balance = (new DereTrialBalanceXmlProjector)->project($operation, $month['book'], $month['identity'], $known, $codes['dere_trial_balance_projection']);

    return ['month' => $month, 'operation' => $operation, 'known' => $known, 'rule' => $rule, 'pgcc' => $pgcc, 'balance' => $balance,
        'rules' => ['closing' => $rule, 'balance' => $codes['dere_trial_balance_projection'], 'deductions' => $codes['dere_deduction_projection'],
            'pgcc_projection' => $codes['dere_pgcc_projection'], 'contributor_projection' => $codes['dere_contributor_projection']]];
}

it('projects D2101 only for independently proven empty unmoved required accounts', function (string $case, array $accounts): void {
    $f = dereDebtNoMovementFixture($case);
    $r = (new DereMonthlyClosingXmlProjector)->project($f['operation'], $f['month'], $f['known'], $f['rules']);
    expect($r['status'])->toBe('PROJECTED')->and($r['period_closed'])->toBeFalse()
        ->and($r['deduction_pgcc_binding']['reportable_amount'])->toBe('100.00')
        ->and($r['deduction_pgcc_binding']['other_financial_deductions'])->toBe('200.00')
        ->and($f['operation']['base']['net_base'])->toBe('700.00');
    $debt = $r['debt_no_movement_projection'];
    expect($debt['account_codes'])->toBe($accounts)->and($debt['production_ready'])->toBeFalse();
    $xml = new DOMDocument;
    $xml->loadXML($debt['xml']);
    expect($xml->getElementsByTagName('semTitulos')->item(0)->textContent)->toBe('1')
        ->and($xml->getElementsByTagName('detTitulo')->length)->toBe(0)
        ->and($xml->getElementsByTagName('tpOper')->item(0)->textContent)->toBe('1');
})->with([
    ['qualified-both-codes', ['debt000', 'debtfund000']], ['qualified-direct-only', ['debt000']],
    ['qualified-fund-only', ['debtfund000']],
]);

it('does not invent D2101 for a PGCC without the official required codes', function (): void {
    $f = dereDebtNoMovementFixture('qualified-no-obligation');
    $r = (new DereMonthlyClosingXmlProjector)->project($f['operation'], $f['month'], $f['known'], $f['rules']);
    expect($r['status'])->toBe('PROJECTED')->and($r)->not->toHaveKey('debt_no_movement_projection');
});

it('blocks the D2101 monthly negative corpus without emitting a closing', function (string $case): void {
    $f = dereDebtNoMovementFixture($case);
    $r = (new DereMonthlyClosingXmlProjector)->project($f['operation'], $f['month'], $f['known'], $f['rules']);
    expect($r['status'])->toBe('ACTION_REQUIRED')->and($r['reason'])->toBe($f['month']['corpus_expectation']['reason'])
        ->and($r['production_ready'])->toBeFalse()->and($r)->not->toHaveKey('xml');
})->with([
    'missing-declaration', 'missing-proof', 'unqualified-proof', 'tampered-proof', 'wrong-proof-supplier',
    'book-hash-conflict', 'future-proof', 'expired-proof', 'incomplete-proof-month', 'missing-account', 'duplicate-account',
    'other-supplier', 'other-period', 'title-detail', 'indicator-conflict', 'nonzero-balance', 'nonzero-movement',
    'generation-before-evidence', 'obligation-conflict', 'unexpected-declaration',
]);

it('validates the unchanged official D2101 1.3 schema including its inert readme', function (): void {
    $f = dereDebtNoMovementFixture();
    $r = (new DereDebtNoMovementXmlProjector)->project($f['month'], $f['pgcc'], $f['balance'], $f['known'], $f['rule']['debt_no_movement_projection']);
    $schema = $f['rule']['debt_no_movement_projection']['xsd'];
    $root = dirname(__DIR__, 3).'/resources/fiscal/sources/official/';
    $validate = static fn (string $xml): array => (new DereXmlSchemaValidator)->validate($xml,
        $root.$schema['source_file'], $schema['source_sha256'], $r['schema'],
        $root.$schema['signature_source_file'], $schema['signature_source_sha256']);
    $v = $validate($r['xml']);
    expect($v['reason'])->toBe('RTC_DERE_UNSIGNED_LOCAL_CANDIDATE')->and($v['unsigned_candidate_structure_checked'])->toBeTrue()
        ->and($v['production_ready'])->toBeFalse()->and($v['signature_verified'])->toBeFalse();
    expect($validate(str_replace('<semTitulos>1</semTitulos>', '<semTitulos>0</semTitulos>', $r['xml']))['reason'])
        ->toBe('RTC_DERE_XML_XSD_INVALID');
});

it('keeps D2101 pending under predecessor contracts and still blocks D1106', function (): void {
    $f = dereDebtNoMovementFixture();
    unset($f['rules']['closing']['debt_no_movement_projection']);
    $r = (new DereMonthlyClosingXmlProjector)->project($f['operation'], $f['month'], $f['known'], $f['rules']);
    expect($r['reason'])->toBe('RTC_DERE_MONTHLY_ADDITIONAL_AUXILIARY_EVENTS_PENDING');
    $f = dereDebtNoMovementFixture();
    $f['month']['prerequisites']['D1106_required'] = true;
    $f['rules']['closing']['pgcc_qualification_required'] = false;
    $f['rules']['closing']['pgcc_projection_required'] = false;
    expect((new DereMonthlyClosingXmlProjector)->project($f['operation'], $f['month'], $f['known'], $f['rules'])['reason'])
        ->toBe('RTC_DERE_MONTHLY_ADDITIONAL_AUXILIARY_EVENTS_PENDING');
});

it('requires qualified context and a bound policy before no movement projection', function (string $case, string $reason): void {
    $f = dereDebtNoMovementFixture();
    $rule = $f['rule']['debt_no_movement_projection'];
    switch ($case) {
        case 'context': $f['pgcc']['status'] = 'ACTION_REQUIRED';
            break;
        case 'balance': $f['balance']['status'] = 'INVALID';
            break;
        case 'code-policy': $rule['allowed_codes'] = ['110112001'];
            break;
        case 'entry': $f['month']['book']['accounts'][4]['financial_entry_ids'] = ['principal'];
            break;
        case 'account-coverage': array_pop($f['month']['book']['accounts']);
            break;
        case 'rule-future': $rule['published_at'] = '2026-10-07';
            break;
    }
    expect((new DereDebtNoMovementXmlProjector)->project($f['month'], $f['pgcc'], $f['balance'], $f['known'], $rule)['reason'])
        ->toBe('RTC_DERE_D2101_'.$reason);
})->with([
    ['context', 'QUALIFIED_CONTEXT_UNPROVEN'], ['balance', 'QUALIFIED_CONTEXT_UNPROVEN'], ['code-policy', 'RULE_UNAVAILABLE'],
    ['entry', 'ACCOUNT_ENTRY_DETAIL_REQUIRED'], ['account-coverage', 'ACCOUNT_COVERAGE_INCOMPLETE'], ['rule-future', 'RULE_UNAVAILABLE'],
]);
