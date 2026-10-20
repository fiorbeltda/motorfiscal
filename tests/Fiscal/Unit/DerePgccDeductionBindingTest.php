<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DerePgccDeductionBindingQualifier;
use App\Modules\Fiscal\ConsumptionReform\DerePgccQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

/** @return array<string, mixed> */
function dereD1121BindingFixture(bool $zero = false): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal/';
    $read = static fn (string $name): array => json_decode(file_get_contents($root.$name), true, 512, JSON_THROW_ON_ERROR);
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.05-20.json');
    $input = $read('scenarios/dere-d1121-binding-2026.10.05-20/financial-scenario.json');
    $month = $read('scenarios/dere-local-2026.10.05-20/qualified-monthly-closing.json');
    if ($zero) {
        $input['facts']['rtc.financial_ledger'][0]['entries'][3]['amount'] = '0.00';
        foreach (['vMovDebt', 'vSaldoFinal', 'vApur'] as $field) {
            $month['book']['accounts'][3][$field] = '0.00';
        }
        unset($month['deductions']);
    }
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input);
    $result = (new RtcScenarioRunService($factory))->evaluate($knowledge, $scenario)->toArray();
    $operation = array_column($result['trace'], null, 'stage')['FINANCIAL_OPERATION_AND_BASE'];
    $codes = $knowledge['documents']['rtc_code_catalog'];
    $rule = $codes['dere_monthly_closing_projection'];
    $known = $scenario->input->context->knowledgeAt;
    $pgcc = (new DerePgccQualifier)->resolve($month['pgcc'], $rule['pgcc_catalogs']['reference'], $rule['pgcc_catalogs']['tax_codes'],
        $known, false, $rule['pgcc_qualification_policy']);

    return ['operation' => $operation, 'month' => $month, 'pgcc' => $pgcc, 'known' => $known, 'rule' => $rule,
        'rules' => ['closing' => $rule, 'balance' => $codes['dere_trial_balance_projection'], 'deductions' => $codes['dere_deduction_projection'],
            'pgcc_projection' => $codes['dere_pgcc_projection'], 'contributor_projection' => $codes['dere_contributor_projection']]];
}

/** @param array<string, mixed> $f @return array<string, mixed> */
function dereD1121BindingResolve(array $f): array
{
    return (new DerePgccDeductionBindingQualifier)->resolve($f['operation'], $f['month'], $f['pgcc'], $f['known'], $f['rule']);
}

/** @param array<string, mixed> $f */
function dereD1121BindingRefresh(array &$f): void
{
    foreach ($f['month']['deductions']['documents'] ?? [] as $index => $doc) {
        $proof = $doc['account_binding_evidence'] ?? null;
        if (! is_array($proof)) {
            continue;
        }
        $proof['value'] = array_intersect_key($doc, array_flip(['chDFe', 'tpDFe', 'dtEmi', 'tpAtiv', 'vDed', 'financial_entry_ids', 'pgcc_account_codes']));
        $proof['value']['period_end'] = $f['month']['book']['period_end'];
        unset($proof['source_hash']);
        $proof['source_hash'] = CanonicalJson::hash($proof);
        $f['month']['deductions']['documents'][$index]['account_binding_evidence'] = $proof;
    }
}

it('reconciles only the reportable correspondent while preserving all financial deductions', function (): void {
    $f = dereD1121BindingFixture();
    $hash = CanonicalJson::hash($f['operation']);
    $r = dereD1121BindingResolve($f);
    expect($r['status'])->toBe('RESOLVED')->and($r['eligible_entry_ids'])->toBe(['correspondent'])
        ->and($r['excluded_deduction_entry_ids'])->toBe(['funding'])->and($r['reportable_amount'])->toBe('100.00')
        ->and($r['other_financial_deductions'])->toBe('200.00')->and($r['operation_hash'])->toBe($hash)
        ->and(count($r['operation']['base']['entries']))->toBe(1)
        ->and($f['operation']['base']['deductions_before_art188'])->toBe('300.00')
        ->and($f['operation']['base']['net_base'])->toBe('700.00')->and(CanonicalJson::hash($f['operation']))->toBe($hash)
        ->and($r['production_ready'])->toBeFalse();
});

it('integrates the mandatory D1121 with PGCC contributor balance and closing', function (): void {
    $f = dereD1121BindingFixture();
    $r = (new DereMonthlyClosingXmlProjector)->project($f['operation'], $f['month'], $f['known'], $f['rules']);
    expect($r['status'])->toBe('PROJECTED')->and($r['deduction_event_required'])->toBeTrue()
        ->and($r['deductions']['deductions']['linked_financial_deductions'])->toBe(1)
        ->and($r['deduction_pgcc_binding']['reportable_amount'])->toBe('100.00')->and($r['period_closed'])->toBeFalse();
    $xml = new DOMDocument;
    $xml->loadXML($r['deductions']['xml']);
    expect($xml->getElementsByTagName('vDed')->item(0)->textContent)->toBe('100.00');
});

it('declares no document deductions when a required account has no appropriation despite other financial costs', function (): void {
    $f = dereD1121BindingFixture(true);
    $r = (new DereMonthlyClosingXmlProjector)->project($f['operation'], $f['month'], $f['known'], $f['rules']);
    expect($r['status'])->toBe('PROJECTED')->and($r['deductions'])->toBeNull()
        ->and($r['deduction_pgcc_binding']['reportable_amount'])->toBe('0.00')
        ->and($r['deduction_pgcc_binding']['other_financial_deductions'])->toBe('200.00');
    $xml = new DOMDocument;
    $xml->loadXML($r['xml']);
    expect($xml->getElementsByTagName('indInexistDedu')->item(0)->textContent)->toBe('1');
});

it('blocks unsupported classification missing mappings and invalid entry or document bindings', function (string $case, string $reason): void {
    $f = dereD1121BindingFixture();
    switch ($case) {
        case 'context': $f['pgcc']['status'] = 'ACTION_REQUIRED';
            break;
        case 'family': $f['operation']['base']['family'] = 'SECURITIZATION_FACTORING_ART193';
            break;
        case 'policy': unset($f['rule']['deduction_entry_mappings']);
            break;
        case 'unknown-code': $f['pgcc']['accounts'][6]['codTrib'] = '411012101';
            break;
        case 'kind': $f['operation']['base']['entries'][3]['kind'] = 'FUNDING_EXPENSE';
            break;
        case 'advisor': $f['operation']['base']['entries'][3]['advisor_category'] = 'INVESTMENT_ADVISOR';
            break;
        case 'bucket': $f['operation']['base']['entries'][3]['bucket'] = 'REVENUE';
            break;
        case 'entry-duplicate': $f['operation']['base']['entries'][] = $f['operation']['base']['entries'][3];
            break;
        case 'book-duplicate': $f['month']['book']['accounts'][3]['financial_entry_ids'][] = 'correspondent';
            break;
        case 'book-incomplete': $f['month']['book']['accounts'][3]['financial_entry_ids'] = [];
            break;
        case 'document': unset($f['month']['deductions']);
            break;
        case 'binding': unset($f['month']['deductions']['documents'][0]['account_binding_evidence']);
            break;
        case 'account': $f['month']['deductions']['documents'][0]['pgcc_account_codes'] = ['funding000'];
            dereD1121BindingRefresh($f);
            break;
        case 'funding': $f['month']['deductions']['documents'][0]['financial_entry_ids'] = ['funding'];
            dereD1121BindingRefresh($f);
            break;
        case 'document-duplicate': $f['month']['deductions']['documents'][] = $f['month']['deductions']['documents'][0];
            break;
        case 'tamper': $f['month']['deductions']['documents'][0]['account_binding_evidence']['value']['vDed'] = '0.00';
            break;
        case 'negative': $f['operation']['base']['entries'][3]['computed_amount'] = '-1.00';
            break;
        case 'observation': $f['month']['deductions']['observed_on'] = '2026-10-01';
            break;
    }
    expect(dereD1121BindingResolve($f)['reason'])->toBe('RTC_DERE_D1121_PGCC_'.$reason);
})->with([
    ['context', 'QUALIFIED_CONTEXT_UNPROVEN'], ['family', 'FINANCIAL_FAMILY_UNSUPPORTED'], ['policy', 'CODE_POLICY_UNPROVEN'],
    ['unknown-code', 'CODE_MAPPING_NOT_SUPPORTED'], ['kind', 'ENTRY_CODE_CLASSIFICATION_CONFLICT'], ['advisor', 'ENTRY_CODE_CLASSIFICATION_CONFLICT'],
    ['bucket', 'ENTRY_CODE_CLASSIFICATION_CONFLICT'], ['entry-duplicate', 'ENTRY_IDENTITY_INVALID'],
    ['book-duplicate', 'ENTRY_LINK_UNKNOWN_OR_DUPLICATE'], ['book-incomplete', 'REPORTABLE_ACCOUNT_AMOUNT_CONFLICT'],
    ['document', 'DOCUMENTS_REQUIRED'], ['binding', 'DOCUMENT_ACCOUNT_EVIDENCE_UNPROVEN'],
    ['account', 'DOCUMENT_PGCC_ACCOUNT_CONFLICT'], ['funding', 'DOCUMENT_ENTRY_NOT_REPORTABLE'],
    ['document-duplicate', 'DOCUMENT_ENTRY_DUPLICATE'], ['tamper', 'DOCUMENT_ACCOUNT_EVIDENCE_UNPROVEN'],
    ['negative', 'AMOUNT_OR_DATE_INVALID'], ['observation', 'DOCUMENT_ACCOUNT_EVIDENCE_UNPROVEN'],
]);

it('requires dated document binding evidence for the actual supplier and accounting month', function (string $field, string $value): void {
    $f = dereD1121BindingFixture();
    $f['month']['deductions']['documents'][0]['account_binding_evidence'][$field] = $value;
    dereD1121BindingRefresh($f);
    expect(dereD1121BindingResolve($f)['status'])->toBe('ACTION_REQUIRED');
})->with([['supplier_id', '22222222000191'], ['observed_on', '2026-10-06'], ['valid_from', '2026-09-02'],
    ['valid_to', '2026-09-29'], ['valid_from', '2026-02-30']]);

it('requires every reportable entry and rejects document detail for a zero appropriation', function (): void {
    $f = dereD1121BindingFixture();
    $second = $f['operation']['base']['entries'][3];
    $second['id'] = 'second';
    $f['operation']['base']['entries'][] = $second;
    $f['month']['book']['accounts'][3]['financial_entry_ids'][] = 'second';
    $f['month']['book']['accounts'][3]['vApur'] = '200.00';
    expect(dereD1121BindingResolve($f)['reason'])->toBe('RTC_DERE_D1121_PGCC_REPORTABLE_ENTRIES_NOT_COMPLETELY_LINKED');
    $zero = dereD1121BindingFixture(true);
    $zero['month']['deductions'] = $f['month']['deductions'];
    expect(dereD1121BindingResolve($zero)['reason'])->toBe('RTC_DERE_D1121_PGCC_UNEXPECTED_DOCUMENT_DETAIL');
});

it('rejects unlinked positive appropriations and a conflicting expense assessment nature', function (string $case, string $reason): void {
    $f = dereD1121BindingFixture();
    if ($case === 'amount') {
        $f['month']['book']['accounts'][3]['vApur'] = '101.00';
    } else {
        $f['month']['book']['accounts'][3]['natVApur'] = 'C';
    }
    expect(dereD1121BindingResolve($f)['reason'])->toBe('RTC_DERE_D1121_PGCC_'.$reason);
})->with([['amount', 'REPORTABLE_ACCOUNT_AMOUNT_CONFLICT'], ['nature', 'REPORTABLE_ACCOUNT_NATURE_CONFLICT']]);

it('preserves original document ceilings when appropriating a PGCC-bound continuation', function (): void {
    $f = dereD1121BindingFixture();
    $root = dirname(__DIR__, 3).'/resources/fiscal/scenarios/dere-d1121-binding-2026.10.05-20/';
    $f['month'] = json_decode(file_get_contents($root.'qualified-continuation.json'), true, 512, JSON_THROW_ON_ERROR);
    $r = (new DereMonthlyClosingXmlProjector)->project($f['operation'], $f['month'], $f['known'], $f['rules']);
    expect($r['status'])->toBe('PROJECTED')->and($r['deduction_pgcc_binding']['reportable_amount'])->toBe('100.00');
    $xml = new DOMDocument;
    $xml->loadXML($r['deductions']['xml']);
    expect($xml->getElementsByTagName('vDedTotal')->length)->toBe(0);
});
