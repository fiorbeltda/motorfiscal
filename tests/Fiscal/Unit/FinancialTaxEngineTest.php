<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\FinancialTaxEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\Core\Shared\LocalDate;

/** @return array<string, mixed> */
function financialTaxOperation(string $date = '2026-09-30', string $base = '800.00'): array
{
    return ['status' => 'RESOLVED', 'qualification' => ['regime' => 'SPECIFIC_FINANCIAL_ART181',
        'supplier_id' => 'supplier', 'base_family' => 'CREDIT_FX_SECURITIES_ART192'],
        'base' => ['status' => 'RESOLVED', 'family' => 'CREDIT_FX_SECURITIES_ART192', 'supplier_id' => 'supplier',
            'period_end' => $date, 'net_base' => $base, 'art188_proportion' => null]];
}

/** @param array<string, mixed> $operation
 * @param  array<string, mixed>  $financialRates
 * @param  array<string, mixed>|null  $testRates
 * @return array<string, mixed>
 */
function financialTaxResult(array $operation, ?bool $fulfilled = true, ?bool $issIncident = null, array $financialRates = ['rates' => []], ?array $testRates = null): array
{
    $testRates ??= json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-rates-2026.09.25-1.json'), true, 512, JSON_THROW_ON_ERROR);

    return (new FinancialTaxEngine(new TemporalRateCatalog($testRates), $financialRates))->resolve($operation,
        LocalDate::fromString($operation['base']['period_end']), new DateTimeImmutable('2026-10-02'), $fulfilled, $issIncident,
        ['strategy' => 'LC214_FINANCIAL_GROSS_DEBITS_ART189_233_348', 'status' => 'ACTIVE', 'published_at' => '2026-10-01',
            'valid_from' => '2026-01-01', 'source_hash' => 'dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde']);
}

it('calculates financial nominal debits on the specific base and dispenses only documented 2026 collection', function (): void {
    $result = financialTaxResult(financialTaxOperation());
    expect($result['status'])->toBe('RESOLVED')->and($result['base'])->toBe('800.00')
        ->and($result['ibs_nominal'])->toBe('0.80')->and($result['cbs_nominal'])->toBe('7.20')
        ->and($result['collection_before_period_credit_offset'])->toBe(['IBS' => '0.00', 'CBS' => '0.00'])
        ->and($result['credit_granted'])->toBeFalse()->and($result['document_approved'])->toBeFalse()
        ->and($result['payment_performed'])->toBeFalse();
});

it('retains gross collection when 2026 accessory obligations were not fulfilled', function (): void {
    $result = financialTaxResult(financialTaxOperation(), false);
    expect($result['collection_before_period_credit_offset'])->toBe(['IBS' => '0.80', 'CBS' => '7.20'])
        ->and($result['collection_status'])->toBe('GROSS_DEBIT_BEFORE_PERIOD_SETTLEMENT');
    expect(financialTaxResult(financialTaxOperation(), null)['reason'])->toBe('RTC_FINANCIAL_TAX_2026_COLLECTION_CONDITION_UNKNOWN');
});

it('rounds each component once including a half cent boundary', function (): void {
    $result = financialTaxResult(financialTaxOperation(base: '5.00'));
    expect($result['ibs_nominal'])->toBe('0.01')->and($result['cbs_nominal'])->toBe('0.05');
});

it('does not turn a legal combined future rate into invented component rates', function (string $date, string $sum, string $reduction): void {
    $result = financialTaxResult(financialTaxOperation($date), true, true);
    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['reason'])->toBe('RTC_FINANCIAL_TAX_RATE_NOT_PROVEN:IBS_STATE')
        ->and($result['combined_rate_percent'])->toBe($sum)->and($result['iss_reduction_pp'])->toBe($reduction);
})->with([
    ['2027-12-31', '10.85', '2.00'], ['2028-12-31', '10.85', '2.00'], ['2029-12-31', '11.00', '1.80'],
    ['2030-12-31', '11.15', '1.60'], ['2031-12-31', '11.30', '1.40'], ['2032-12-31', '11.50', '1.20'],
    ['2033-12-31', '12.50', '0.00'], ['2034-12-31', '12.50', '0.00'],
]);

/** Synthetic rates exercise the algorithm; they are never published into a candidate bundle.
 * @return array<string, mixed>
 */
function syntheticFinancialRates(): array
{
    $rows = [];
    foreach ([false, true] as $iss) {
        foreach (['IBS_STATE' => '0.05', 'IBS_MUNICIPAL' => '0.05', 'CBS' => $iss ? '8.75' : '10.75'] as $tax => $percent) {
            $rows[] = ['tax' => $tax, 'jurisdiction' => 'BR', 'percent' => $percent, 'valid_from' => '2027-01-01',
                'valid_to' => '2027-12-31', 'published_at' => '2026-10-01', 'status' => 'ACTIVE',
                'source_id' => 'SYNTHETIC.NOT.AN.OFFICIAL.ACT', 'source_hash' => str_repeat('a', 64),
                'regime' => 'FINANCIAL_ART189', 'iss_incident' => $iss,
                'source_act_type' => $tax === 'CBS' ? 'UNION_FINANCIAL_RATE_ACT' : 'CGIBS_FINANCIAL_RATE_ACT'];
        }
    }

    return ['version' => 'SYNTHETIC.TEST.ONLY', 'rates' => $rows];
}

it('selects separately published component rows by ISS incidence without cross scope conflicts', function (): void {
    $operation = financialTaxOperation('2027-12-31', '1000.00');
    $noIss = financialTaxResult($operation, true, false, syntheticFinancialRates());
    $iss = financialTaxResult($operation, true, true, syntheticFinancialRates());
    expect($noIss['status'])->toBe('RESOLVED')->and($noIss['cbs_nominal'])->toBe('107.50')
        ->and($iss['status'])->toBe('RESOLVED')->and($iss['cbs_nominal'])->toBe('87.50');
});

it('requires ISS scope and a specific published act and rejects a conflicting combined rate', function (): void {
    $operation = financialTaxOperation('2027-12-31');
    expect(financialTaxResult($operation)['reason'])->toBe('RTC_FINANCIAL_TAX_ISS_INCIDENCE_REQUIRED_FOR_COMBINED_RATE');
    $rates = syntheticFinancialRates();
    $rates['rates'][0]['source_act_type'] = 'GENERAL_REFERENCE_RATE';
    expect(financialTaxResult($operation, true, false, $rates)['reason'])->toBe('RTC_FINANCIAL_TAX_FINANCIAL_RATE_ACT_NOT_PROVEN:IBS_STATE');
    $rates = syntheticFinancialRates();
    $rates['rates'][2]['percent'] = '10.70';
    expect(financialTaxResult($operation, true, false, $rates)['reason'])->toBe('RTC_FINANCIAL_TAX_PUBLISHED_COMPONENTS_COMBINED_RATE_CONFLICT');
});

it('requires a rule for negative base and an allocation for mixed zero cooperative operations', function (): void {
    expect(financialTaxResult(financialTaxOperation(base: '-1.00'))['reason'])->toBe('RTC_FINANCIAL_TAX_NEGATIVE_PERIOD_BASE_RULE_REQUIRED');
    $operation = financialTaxOperation();
    $operation['base']['art188_proportion'] = ['numerator' => '1.00', 'denominator' => '3.00'];
    expect(financialTaxResult($operation)['reason'])->toBe('RTC_FINANCIAL_TAX_COOPERATIVE_MIXED_RATE_ALLOCATION_REQUIRED');
});

it('rejects a mismatched supplier family and unqualified operation', function (string $failure): void {
    $operation = financialTaxOperation();
    if ($failure === 'supplier') {
        $operation['base']['supplier_id'] = 'other';
    } elseif ($failure === 'family') {
        $operation['qualification']['base_family'] = 'LEASING';
    } else {
        $operation['status'] = 'ACTION_REQUIRED';
    }
    expect(financialTaxResult($operation)['status'])->toBe('INVALID');
})->with(['supplier', 'family', 'unqualified']);
