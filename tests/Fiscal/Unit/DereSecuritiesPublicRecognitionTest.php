<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/TvmPublicRecognitionFixtures.php';

use App\Modules\Fiscal\ConsumptionReform\FinancialPublicOfferDebtQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DereMonthlyClosingXmlProjector;

it('releases already appropriated public yield at book value without taxing it a second time', function (string $case): void {
    [$scenario, $catalog, $known, $fixture] = tvmPublicRecognitionFixture($case);
    $operation = tvmPublicRecognitionOperation($scenario);
    $month = tvmPublicRecognitionMonth($scenario, $operation, $fixture);
    $result = (new DereMonthlyClosingXmlProjector)->project($operation, $month, $known,
        ['closing' => $catalog['dere_monthly_closing_projection'], 'balance' => $catalog['dere_trial_balance_projection'],
            'deductions' => $catalog['dere_deduction_projection'], 'pgcc_projection' => $catalog['dere_pgcc_projection'],
            'contributor_projection' => $catalog['dere_contributor_projection']]);
    expect($result['status'])->toBe('PROJECTED')->and($operation['base']['net_base'])->toBe($fixture['expected']['base']);
    $yield = $result['debt_yield_projection'];
    $qualification = $yield['qualified_entries']['yield1'];
    $binding = $qualification['sale_recognition_binding'];
    expect($yield['total_vApur'])->toBe($fixture['expected']['auxiliary_base'])
        ->and($qualification['xml_title']['vVarMensal'])->toBe('100.00')->and($qualification['book_apur_amount'])->toBe('0.00')
        ->and($binding['current_curve_return_not_reincluded'])->toBe($fixture['expected']['released_curve'])
        ->and($binding['additional_sale_taxable_amount'])->toBe('0.00')->and($binding['production_ready'])->toBeFalse()
        ->and($binding['analysis']['recognition_inventory']['status'])->toBe('RECONCILED');
    if (getenv('TVM_PUBLIC_RECOGNITION_CAPTURE') === '1') {
        foreach (['financial' => $scenario, 'month' => $month] as $suffix => $data) {
            $path = dirname(__DIR__, 3).'/resources/fiscal/scenarios/tvm-public-recognition-2026.10.07-28-draft10/'.$case.'-'.$suffix.'.json';
            if (is_file($path)) {
                expect(CanonicalJson::encode(json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)))->toBe(CanonicalJson::encode($data));
            } else {
                $stream = fopen($path, 'x');
                expect($stream)->not->toBeFalse();
                fwrite($stream, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
                fclose($stream);
            }
        }
    }
})->with(['partial', 'complete', 'exclusive-fund', 'two-sales']);

it('does not treat new or offsetting sale gains as already recognized public yield', function (string $case): void {
    [$scenario, $catalog, $known] = tvmPublicRecognitionFixture($case);
    $ledger = $scenario['facts']['rtc.financial_ledger'][0];
    $result = (new FinancialPublicOfferDebtQualifier)->resolve($ledger['entries'][4], $ledger, $known, $catalog['financial_operation']['base']['public_offer_debt']);
    expect($result['reason'])->toBe('RTC_FINANCIAL_ART195_SALE_NEW_RESULT_OR_FAIR_VALUE_TREATMENT_PENDING');
})->with(['new-produced', 'offsetting-new-produced']);

it('requires actual prior fiscal replay when a position is not a first acquisition', function (): void {
    [$scenario, $catalog, $known] = tvmPublicRecognitionFixture('partial');
    $ledger = $scenario['facts']['rtc.financial_ledger'][0];
    $bridge = $ledger['entries'][4]['public_offer_debt']['sale_recognition'];
    $path = dirname(__DIR__, 3).'/resources/fiscal/scenarios/dere-variable-yield-2026.10.06-27/partial-sale-gain-financial.json';
    $prior = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $ledger['entries'][4] = $prior['facts']['rtc.financial_ledger'][0]['entries'][4];
    $detail = &$ledger['entries'][4]['public_offer_debt'];
    $bridge['analysis_input']['position'] = $detail['position'];
    $bridge['analysis_input']['title'] = $detail['title'];
    $detail['sale_recognition'] = $bridge;
    unset($detail);
    $result = (new FinancialPublicOfferDebtQualifier)->resolve($ledger['entries'][4], $ledger, $known, $catalog['financial_operation']['base']['public_offer_debt']);
    expect($result['reason'])->toBe('RTC_TVM_REALIZATION_REPLAYED_FISCAL_HISTORY_MISSING');
});

it('requires the bound policy independent sale evidence and complete recognition inventory', function (string $mutation, string $reason): void {
    [$scenario, $catalog, $known] = tvmPublicRecognitionFixture('partial');
    $ledger = $scenario['facts']['rtc.financial_ledger'][0];
    $entry = &$ledger['entries'][4];
    $rule = $catalog['financial_operation']['base']['public_offer_debt'];
    switch ($mutation) {
        case 'missing': unset($entry['public_offer_debt']['sale_recognition']);
            break;
        case 'policy': unset($rule['sale_recognition_policy']);
            break;
        case 'proof': unset($entry['public_offer_debt']['sale_recognition']['independent_evidence']);
            break;
        case 'scope': $entry['public_offer_debt']['sale_recognition']['analysis_input']['title']['cCta'] = 'other000';
            break;
        case 'sale-proof': unset($entry['public_offer_debt']['sale_recognition']['analysis_input']['realizations'][0]['component_evidence']);
            break;
        case 'coverage': $entry['public_offer_debt']['sale_recognition']['analysis_input']['realizations'] = [];
            break;
        case 'calculation-policy': $rule['sale_recognition_policy']['analysis']['daily_memory_calculation']['published_at'] = '2026-10-01T00:00:00+00:00';
            break;
        case 'required-history': $rule['sale_recognition_policy']['analysis']['historical_snapshot_required'] = false;
            break;
        case 'separate-security-entry': $other = $ledger['entries'][0];
            $other['id'] = 'manual-yield';
            $other['document_id'] = 'manual-yield-document';
            $other['security_identity'] = array_intersect_key($entry['public_offer_debt']['title'], array_flip(['idTitulo', 'cCta', 'CNPJDevedor', 'currency']));
            $ledger['entries'][] = $other;
            break;
    }
    unset($entry);
    $result = (new FinancialPublicOfferDebtQualifier)->resolve($ledger['entries'][4], $ledger, $known, $rule);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason'])->toBe($reason)->and($result)->not->toHaveKey('xml');
})->with([
    ['missing', 'RTC_FINANCIAL_ART195_DAILY_SALE_GAIN_TAX_TREATMENT_PENDING'],
    ['policy', 'RTC_FINANCIAL_ART195_SALE_RECOGNITION_POLICY_UNAVAILABLE'],
    ['proof', 'RTC_FINANCIAL_ART195_INDEPENDENT_SALE_RECOGNITION_UNPROVEN'],
    ['scope', 'RTC_FINANCIAL_ART195_SALE_RECOGNITION_SCOPE_CONFLICT'],
    ['sale-proof', 'RTC_TVM_REALIZATION_INDEPENDENT_COMPONENTS_UNPROVEN'],
    ['coverage', 'RTC_TVM_REALIZATION_SALE_COVERAGE_INCOMPLETE'],
    ['calculation-policy', 'RTC_FINANCIAL_ART195_SALE_RECOGNITION_POLICY_UNAVAILABLE'],
    ['required-history', 'RTC_FINANCIAL_ART195_SALE_RECOGNITION_POLICY_UNAVAILABLE'],
    ['separate-security-entry', 'RTC_FINANCIAL_ART195_SALE_RECOGNITION_SEPARATE_SECURITY_ENTRY_PENDING'],
]);
