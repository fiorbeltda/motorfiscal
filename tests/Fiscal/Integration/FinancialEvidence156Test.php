<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\FinancialTaxEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;

it('exercises each supported financial base with separately marked synthetic future components', function (string $family, int $year): void {
    $sum = match ($year) {
        2027 => '10.85', 2030 => '11.15', 2033 => '12.50'
    };
    $cbs = DecimalValue::fromString($sum)->minus(DecimalValue::fromString('2.00'))->value();
    $catalog = [TemporalRateCatalog::EVIDENCE_KEY => TemporalRateCatalog::EVIDENCE_POLICY, 'version' => 'SYNTHETIC.FIN156', 'rates' => []];
    foreach ([['IBS_STATE', '1.25'], ['IBS_MUNICIPAL', '0.75'], ['CBS', $cbs]] as [$tax, $percent]) {
        $catalog['rates'][] = ['tax' => $tax, 'jurisdiction' => 'BR', 'percent' => $percent, 'iss_incident' => false,
            'valid_from' => $year.'-01-01', 'valid_to' => $year.'-12-31', 'published_at' => '2026-01-01T00:00:00Z',
            'status' => 'ACTIVE', 'source_kind' => 'SYNTHETIC_TEST', 'synthetic' => true, 'regime' => 'FINANCIAL_ART189',
            'source_act_type' => $tax === 'CBS' ? 'UNION_FINANCIAL_RATE_ACT' : 'CGIBS_FINANCIAL_RATE_ACT',
            'source_id' => 'SYNTHETIC.FINANCIAL.COMPONENT156', 'source_hash' => hash('sha256', 'SYNTHETIC.FIN156')];
    }
    $operation = ['status' => 'RESOLVED', 'qualification' => ['regime' => 'SPECIFIC_FINANCIAL_ART181',
        'supplier_id' => 'SYNTHETIC.SUPPLIER', 'base_family' => $family], 'base' => ['status' => 'RESOLVED',
            'family' => $family, 'supplier_id' => 'SYNTHETIC.SUPPLIER', 'period_end' => $year.'-10-01', 'net_base' => '800.00']];
    $result = (new FinancialTaxEngine(new TemporalRateCatalog(['rates' => []]), $catalog))->resolve($operation,
        LocalDate::fromString($year.'-10-01'), new DateTimeImmutable($year.'-11-05'), true, false,
        ['strategy' => 'LC214_FINANCIAL_GROSS_DEBITS_ART189_233_348', 'status' => 'ACTIVE', 'published_at' => '2026-01-01',
            'valid_from' => '2026-01-01', 'source_hash' => hash('sha256', 'SYNTHETIC.FIN156')]);
    expect($result['status'])->toBe('RESOLVED')->and($result['components']['IBS_STATE']['nominal_amount'])->toBe('10.00')
        ->and($result['components']['IBS_MUNICIPAL']['nominal_amount'])->toBe('6.00')
        ->and($result['ibs_nominal'])->toBe('16.00')
        ->and($result['cbs_nominal'])->toBe(DecimalValue::fromString($cbs)->multipliedBy(DecimalValue::fromString('8'))->rounded(2)->value())
        ->and($result['credit_granted'])->toBeFalse()->and($result['document_approved'])->toBeFalse()
        ->and($result['payment_performed'])->toBeFalse();
    unset($catalog[TemporalRateCatalog::EVIDENCE_KEY]);
    $denied = (new TemporalRateCatalog($catalog))->resolve('IBS_MUNICIPAL', 'BR', LocalDate::fromString($year.'-10-01'), new DateTimeImmutable($year.'-11-05'));
    expect($denied['status'])->toBe('NOT_FOUND')->and($denied['official'])->toBeFalse();
})->with(['CREDIT_FX_SECURITIES_ART192', 'SECURITIZATION_FACTORING_ART193'])->with([2027, 2030, 2033]);
