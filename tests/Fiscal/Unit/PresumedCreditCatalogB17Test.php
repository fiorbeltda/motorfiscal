<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\PresumedCreditCatalogB17;

function b17Law(): string
{
    static $text = null;
    if ($text === null) {
        $raw = (string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/sources/official/lc214-planalto-observed-20261103.htm');
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }
        $text = (string) preg_replace('/\s+/u', ' ', html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', $raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    return $text;
}

function b17In(string $hypothesis, array $extra = []): array
{
    return ['hypothesis' => $hypothesis, 'acquirer_regime' => 'REGULAR', 'base_amount' => '1000.00', 'acquisition_date' => '2030-05-10',
        'document_id' => 'NFE-1', ...$extra];
}

it('binds every hypothesis to a unique, current excerpt of the official law', function (): void {
    $text = b17Law();
    foreach (PresumedCreditCatalogB17::HYPOTHESES as $key => $h) {
        expect(substr_count($text, $h[3]))->toBe(1, $key);
        $after = substr($text, strpos($text, $h[3]) + strlen($h[3]), 2500);
        $later = strpos($after, substr($h[3], 0, 45));
        expect($later === false || ! str_contains(substr($after, $later, 900), 'Redação dada'))->toBeTrue($key);
    }
    expect(hash_file('sha256', dirname(__DIR__, 3).'/resources/fiscal/sources/official/lc214-planalto-observed-20261103.htm'))->toBe(PresumedCreditCatalogB17::SOURCE_SHA256);
});

it('computes the statutory ZFM/ALC presumed IBS credit by origin region (arts. 447 and 465)', function (string $h, string $region, string $amount): void {
    $r = (new PresumedCreditCatalogB17)->qualify(b17In($h, ['habilitated' => true, 'zero_rate_operation' => true,
        'habilitation_evidence_id' => 'SUFRAMA-1', 'origin_region' => $region]));
    expect($r['status'])->toBe('PRESUMED_CREDIT_HYPOTHESIS_QUALIFIED')->and($r['amounts'])->toBe(['IBS' => $amount])
        ->and($r['official_decision'])->toBeTrue()->and($r['credit_appropriated'])->toBeFalse()->and($r['usage_restriction'])->toContain('art. 4');
})->with([
    ['ZFM_NATIONAL_INDUSTRIALIZED_GOOD', 'SOUTH_SOUTHEAST_EXCEPT_ES', '75.00'],
    ['ZFM_NATIONAL_INDUSTRIALIZED_GOOD', 'NORTH_NORTHEAST_CENTERWEST_OR_ES', '135.00'],
    ['ALC_NATIONAL_INDUSTRIALIZED_GOOD', 'SOUTH_SOUTHEAST_EXCEPT_ES', '75.00'],
    ['ALC_NATIONAL_INDUSTRIALIZED_GOOD', 'NORTH_NORTHEAST_CENTERWEST_OR_ES', '135.00'],
]);

it('follows the art. 170 schedule by year and never invents an IBS rate before 2029', function (string $date, ?string $ibs, string $status): void {
    $r = (new PresumedCreditCatalogB17)->qualify(b17In('SOLID_WASTE_FROM_INCENTIVIZED_COLLECTOR', ['acquisition_date' => $date, 'waste_category' => 'PAPER']));
    expect($r['status'])->toBe($status);
    if ($status === 'PRESUMED_CREDIT_HYPOTHESIS_QUALIFIED') {
        expect($r['amounts'])->toBe(['IBS' => $ibs, 'CBS' => '70.00']);
    }
})->with([
    ['2026-06-01', null, 'ACTION_REQUIRED'],
    ['2027-06-01', null, 'PRESUMED_CREDIT_HYPOTHESIS_QUALIFIED'],
    ['2028-12-31', null, 'PRESUMED_CREDIT_HYPOTHESIS_QUALIFIED'],
    ['2029-01-01', '13.00', 'PRESUMED_CREDIT_HYPOTHESIS_QUALIFIED'],
    ['2030-05-10', '26.00', 'PRESUMED_CREDIT_HYPOTHESIS_QUALIFIED'],
    ['2031-05-10', '39.00', 'PRESUMED_CREDIT_HYPOTHESIS_QUALIFIED'],
    ['2032-05-10', '52.00', 'PRESUMED_CREDIT_HYPOTHESIS_QUALIFIED'],
    ['2033-01-01', '130.00', 'PRESUMED_CREDIT_HYPOTHESIS_QUALIFIED'],
    ['2040-01-01', '130.00', 'PRESUMED_CREDIT_HYPOTHESIS_QUALIFIED'],
]);

it('applies the art. 170, §3º exclusions and the §4º lubricating-oil exception', function (): void {
    $c = new PresumedCreditCatalogB17;
    foreach (PresumedCreditCatalogB17::SOLID_WASTE_EXCLUSIONS as $category) {
        expect($c->qualify(b17In('SOLID_WASTE_FROM_INCENTIVIZED_COLLECTOR', ['waste_category' => $category]))['reason'])->toBe('B17_SOLID_WASTE_EXCLUDED_ART170_3');
    }
    expect($c->qualify(b17In('SOLID_WASTE_FROM_INCENTIVIZED_COLLECTOR', ['waste_category' => 'LUBRICATING_OILS', 'anp_authorized_rerefiner_or_collector' => true]))['status'])
        ->toBe('PRESUMED_CREDIT_HYPOTHESIS_QUALIFIED');
});

it('requires an annual act or reference parameter, and marks synthetic parameters as non-official', function (string $h, string $missingReason): void {
    $c = new PresumedCreditCatalogB17;
    $missing = $c->qualify(b17In($h));
    $synthetic = $c->qualify(b17In($h, ['rate_parameters' => ['source_id' => 'SYNTHETIC.B17', 'source_kind' => 'SYNTHETIC_TEST', 'ibs_percent' => '2.5', 'cbs_percent' => '1.5']]));
    $official = $c->qualify(b17In($h, ['rate_parameters' => ['source_id' => 'ATO-CONJUNTO-2030', 'source_kind' => 'OFFICIAL', 'ibs_percent' => '2.5', 'cbs_percent' => '1.5']]));
    expect($missing['reason'])->toBe($missingReason)
        ->and($synthetic['status'])->toBe('PRESUMED_CREDIT_SYNTHETIC_TEST_ONLY')->and($synthetic['official_decision'])->toBeFalse()
        ->and($synthetic['amounts'])->toBe(['IBS' => '25.00', 'CBS' => '15.00'])
        ->and($official['status'])->toBe('PRESUMED_CREDIT_HYPOTHESIS_QUALIFIED')->and($official['official_decision'])->toBeTrue();
})->with([
    ['RURAL_PRODUCER_NON_CONTRIBUTOR', 'B17_ANNUAL_JOINT_ACT_PERCENT_REQUIRED'],
    ['AUTONOMOUS_CARGO_CARRIER_INDIVIDUAL', 'B17_ANNUAL_JOINT_ACT_PERCENT_REQUIRED'],
    ['USED_MOVABLE_GOOD_FOR_RESALE', 'B17_REFERENCE_RATE_PARAMETER_REQUIRED'],
]);

it('computes the art. 449 intermediate-good credit at 7.5%, on value added for industrialization by order', function (): void {
    $c = new PresumedCreditCatalogB17;
    $base = ['habilitated' => true, 'zero_rate_operation' => true, 'used_in_final_goods_production' => true, 'habilitation_evidence_id' => 'SUFRAMA-PPB'];
    expect($c->qualify(b17In('ZFM_INTERMEDIATE_GOOD_INCENTIVIZED_INDUSTRY', $base))['amounts'])->toBe(['IBS' => '75.00'])
        ->and($c->qualify(b17In('ZFM_INTERMEDIATE_GOOD_INCENTIVIZED_INDUSTRY', [...$base, 'industrialization_by_order_return' => true, 'value_added_amount' => '200.00']))['amounts'])->toBe(['IBS' => '15.00'])
        ->and($c->qualify(b17In('ZFM_INTERMEDIATE_GOOD_INCENTIVIZED_INDUSTRY', [...$base, 'industrialization_by_order_return' => true]))['reason'])->toBe('B17_VALUE_ADDED_REQUIRED_ART449_2')
        ->and($c->qualify(b17In('ZFM_INTERMEDIATE_GOOD_INCENTIVIZED_INDUSTRY', [...$base, 'used_in_final_goods_production' => false]))['reason'])->toBe('B17_INCENTIVIZED_INDUSTRY_PROOF_REQUIRED');
});

it('computes the art. 444 import credit as half the import IBS rate, allowing Simples, only with a sourced rate', function (): void {
    $c = new PresumedCreditCatalogB17;
    $base = ['habilitated' => true, 'habilitation_evidence_id' => 'SUFRAMA-1', 'acquirer_regime' => 'SIMPLES'];
    $missing = $c->qualify(b17In('ZFM_IMPORT_FOR_PRESENTIAL_RESALE', $base));
    $synthetic = $c->qualify(b17In('ZFM_IMPORT_FOR_PRESENTIAL_RESALE', [...$base, 'rate_parameters' => ['source_id' => 'SYNTHETIC.B17.IMPORT', 'source_kind' => 'SYNTHETIC_TEST', 'import_ibs_percent' => '17.7']]));
    expect($missing['reason'])->toBe('B17_IMPORT_IBS_RATE_PARAMETER_REQUIRED')
        ->and($synthetic['status'])->toBe('PRESUMED_CREDIT_SYNTHETIC_TEST_ONLY')->and($synthetic['amounts'])->toBe(['IBS' => '88.50'])
        ->and($synthetic['official_decision'])->toBeFalse();
});

it('computes the ALC import (art. 462) and ALC industry CBS 6% (art. 467) credits with their exclusions', function (): void {
    $c = new PresumedCreditCatalogB17;
    $imp = $c->qualify(b17In('ALC_IMPORT_FOR_PRESENTIAL_RESALE', ['acquirer_regime' => 'SIMPLES', 'habilitated' => true, 'habilitation_evidence_id' => 'H',
        'rate_parameters' => ['source_id' => 'SYNTHETIC.ALC', 'source_kind' => 'SYNTHETIC_TEST', 'import_ibs_percent' => '10']]));
    $ind = ['habilitated' => true, 'habilitation_evidence_id' => 'H', 'operation_standard_incidence' => true, 'art441_excluded_good' => false];
    expect($imp['amounts'])->toBe(['IBS' => '50.00'])->and($imp['official_decision'])->toBeFalse()
        ->and($c->qualify(b17In('ALC_INDUSTRY_OUTPUT_CBS', $ind))['amounts'])->toBe(['CBS' => '60.00'])
        ->and($c->qualify(b17In('ALC_INDUSTRY_OUTPUT_CBS', [...$ind, 'operation_standard_incidence' => false]))['reason'])->toBe('B17_OPERATION_EXCLUDED_ART450_3_OR_467_2')
        ->and($c->qualify(b17In('ALC_INDUSTRY_OUTPUT_CBS', [...$ind, 'art441_excluded_good' => true]))['reason'])->toBe('B17_OPERATION_EXCLUDED_ART450_3_OR_467_2');
});

it('computes the ZFM industry credit (art. 450): IBS by good category on the IBS debit balance and CBS 6% or 2%', function (string $category, bool $art454, string $ibs, string $cbs): void {
    $r = (new PresumedCreditCatalogB17)->qualify(b17In('ZFM_INCENTIVIZED_INDUSTRY_OUTPUT', ['habilitated' => true, 'habilitation_evidence_id' => 'H',
        'operation_standard_incidence' => true, 'art441_excluded_good' => false, 'good_category' => $category, 'ibs_debit_balance' => '400.00', 'art454_product' => $art454]));
    expect($r['status'])->toBe('PRESUMED_CREDIT_HYPOTHESIS_QUALIFIED')->and($r['amounts'])->toBe(['IBS' => $ibs, 'CBS' => $cbs]);
})->with([
    ['FINAL_CONSUMER_GOOD', false, '220.00', '20.00'],
    ['CAPITAL_GOOD', true, '300.00', '60.00'],
    ['INTERMEDIATE_GOOD', false, '361.00', '20.00'],
    ['IT_GOOD_OR_ICMS_100', false, '400.00', '20.00'],
]);

it('refuses the art. 450 Union-sale special case and missing category', function (): void {
    $c = new PresumedCreditCatalogB17;
    $base = ['habilitated' => true, 'habilitation_evidence_id' => 'H', 'operation_standard_incidence' => true, 'art441_excluded_good' => false];
    expect($c->qualify(b17In('ZFM_INCENTIVIZED_INDUSTRY_OUTPUT', [...$base, 'sale_to_union_art473' => true, 'good_category' => 'CAPITAL_GOOD', 'ibs_debit_balance' => '1.00']))['reason'])->toBe('B17_ART450_5_UNION_SALE_NOT_MODELED')
        ->and($c->qualify(b17In('ZFM_INCENTIVIZED_INDUSTRY_OUTPUT', $base))['reason'])->toBe('B17_ART450_CATEGORY_AND_IBS_DEBIT_BALANCE_REQUIRED');
});

it('computes the automotive CBS credit by fruition month with the 2029-2032 phase-out (art. 311)', function (string $date, int $month, ?string $amount, string $reason): void {
    $r = (new PresumedCreditCatalogB17)->qualify(b17In('AUTOMOTIVE_PROJECT_ART11C_LAW9440', ['acquisition_date' => $date, 'habilitated' => true,
        'eligible_electric_or_biofuel_hybrid' => true, 'habilitation_evidence_id' => 'MDIC-1', 'fruition_month' => $month, 'full_cbs_domestic_sale' => true]));
    expect($r['reason'])->toBe($reason);
    if ($amount !== null) {
        expect($r['amounts'])->toBe(['CBS' => $amount]);
    }
})->with([
    ['2027-03-01', 1, '116.00', 'B17_PRESUMED_CREDIT_COMPUTED'],
    ['2027-03-01', 13, '100.00', 'B17_PRESUMED_CREDIT_COMPUTED'],
    ['2028-03-01', 49, '87.00', 'B17_PRESUMED_CREDIT_COMPUTED'],
    ['2029-03-01', 13, '80.00', 'B17_PRESUMED_CREDIT_COMPUTED'],
    ['2032-03-01', 13, '20.00', 'B17_PRESUMED_CREDIT_COMPUTED'],
    ['2033-01-01', 13, null, 'B17_AUTOMOTIVE_CREDIT_EXTINCT_OR_PERIOD_EXCEEDED'],
    ['2027-03-01', 61, null, 'B17_AUTOMOTIVE_CREDIT_EXTINCT_OR_PERIOD_EXCEEDED'],
]);

it('records art. 312, 380 and 381 as dependent on external formula, legacy data or executive act', function (string $h, string $reason): void {
    expect((new PresumedCreditCatalogB17)->qualify(b17In($h))['reason'])->toBe($reason);
})->with([
    ['AUTOMOTIVE_PROJECT_LAW9826', 'B17_ART312_FACTORS_FROM_EXTERNAL_LEGISLATION_REQUIRED'],
    ['TRANSITION_STOCK_2027', 'B17_ART381_EXECUTIVE_ACT_FOR_STOCK_VERIFICATION_REQUIRED'],
    ['TRANSITION_DEPRECIATION_PIS_COFINS', 'B17_ART380_LEGACY_PIS_COFINS_SCHEDULE_REQUIRED'],
]);

it('refuses unqualified inputs', function (array $in, string $reason): void {
    expect((new PresumedCreditCatalogB17)->qualify($in)['reason'])->toBe($reason);
})->with([
    'unknown hypothesis' => [b17In('CREDITO_GENERICO'), 'B17_HYPOTHESIS_NOT_IN_CATALOGUE'],
    'non-regular acquirer' => [b17In('ZFM_NATIONAL_INDUSTRIALIZED_GOOD', ['acquirer_regime' => 'SIMPLES']), 'B17_ACQUIRER_NOT_REGULAR_REGIME'],
    'missing document' => [b17In('ZFM_NATIONAL_INDUSTRIALIZED_GOOD', ['document_id' => ' ']), 'B17_DOCUMENT_REQUIRED'],
    'float base' => [b17In('ZFM_NATIONAL_INDUSTRIALIZED_GOOD', ['base_amount' => '1e3']), 'B17_BASE_OR_DATE_INVALID'],
    'no habilitation proof' => [b17In('ZFM_NATIONAL_INDUSTRIALIZED_GOOD', ['habilitated' => true, 'zero_rate_operation' => true]), 'B17_HABILITATION_AND_ZERO_RATE_PROOF_REQUIRED'],
    'no region' => [b17In('ALC_NATIONAL_INDUSTRIALIZED_GOOD', ['habilitated' => true, 'zero_rate_operation' => true, 'habilitation_evidence_id' => 'X']), 'B17_ORIGIN_REGION_REQUIRED'],
    'malformed parameter' => [b17In('RURAL_PRODUCER_NON_CONTRIBUTOR', ['rate_parameters' => ['source_id' => 'A', 'source_kind' => 'OFFICIAL', 'ibs_percent' => 'x', 'cbs_percent' => '1']]), 'B17_RATE_PARAMETER_INVALID'],
    'unknown source kind' => [b17In('RURAL_PRODUCER_NON_CONTRIBUTOR', ['rate_parameters' => ['source_id' => 'A', 'source_kind' => 'ESTIMATE', 'ibs_percent' => '1', 'cbs_percent' => '1']]), 'B17_ANNUAL_JOINT_ACT_PERCENT_REQUIRED'],
]);
