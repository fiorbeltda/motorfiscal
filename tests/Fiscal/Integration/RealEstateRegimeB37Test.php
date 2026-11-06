<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\RealEstateRegimeB37;

require_once dirname(__DIR__, 3).'/scripts/agent_b/real_estate_b37_fixture.php';

// B37 (4B.4.10): regime específico de bens imóveis, operações em NFS-e (LC 214, arts. 251-265).

it('quotes the regime literally from the hashed Planalto snapshot', function (): void {
    $path = resource_path('fiscal/sources/official/lc214-planalto-observed-20261103.htm');
    expect(hash_file('sha256', $path))->toBe(RealEstateRegimeB37::POLICY['source_hash']);
    $raw = (string) file_get_contents($path);
    if (! mb_check_encoding($raw, 'UTF-8')) {
        $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    }
    $text = (string) preg_replace('/\s+/u', ' ', html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', $raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    foreach (['As alíquotas do IBS e da CBS relativas às operações de locação, cessão onerosa e arrendamento de bens imóveis ficam reduzidas em 70% (setenta por cento).',
        'As alíquotas do IBS e da CBS relativas às operações de que trata este Capítulo ficam reduzidas em 50% (cinquenta por cento).',
        'Não serão computados no valor da locação, cessão onerosa ou arrendamento de bem imóvel: I - o valor dos tributos e dos emolumentos incidentes sobre o bem imóvel; e II - as despesas de condomínio.',
        'redutor social no valor de R$ 600,00 (seiscentos reais), por mês, por bem imóvel, até o limite do valor da base de cálculo',
        'com período não superior a 90 (noventa) dias ininterruptos, serão tributados de acordo com as mesmas regras aplicáveis aos serviços de hotelaria',
        'na locação, cessão onerosa ou arrendamento de bem imóvel, no momento do pagamento'] as $excerpt) {
        expect(mb_substr_count($text, $excerpt))->toBeGreaterThanOrEqual(1);
    }
});

it('taxes a non-residential lease at 70% reduction on rent net of property taxes and condominium', function (): void {
    $result = realEstateB37Evaluate();
    expect($result['status'])->toBe('VALID');
    $decision = $result['decision'];
    expect($decision['base']['taxable'])->toBe('700.00')
        ->and($decision['treatment']['regime'])->toBe('REAL_ESTATE_SPECIFIC')
        ->and($decision['treatment']['rate_reduction_percent'])->toBe('70.00')
        ->and($decision['classification']['cclasstrib'])->toBe('200027')
        ->and($decision['classification']['cindop'])->toBe('020101')
        ->and($decision['document']['national_enrichment']['IBSCBS.valores.fed.pRedAliqCBS'])->toBe('70.00')
        // 2026 test rates reduced by 70%: CBS 0.27% and IBS 0.03% of 700.00.
        ->and($decision['cbs']['federal_rate_percent'])->toBe('0.27')
        ->and($decision['cbs']['nominal_amount'])->toBe('1.89')
        ->and($decision['ibs']['nominal_amount'])->toBe('0.21');
});

it('deducts the residential social reducer only with an updated value and its source, capped at the base', function (): void {
    $social = ['monthly_value_per_property' => '612.34', 'properties' => 1, 'months' => 1,
        'value_source_id' => 'FIXTURE.IPCA.UPDATE', 'value_source_hash' => hash('sha256', 'fixture IPCA-updated reducer')];
    $result = realEstateB37WithSettlement(['social_reducer' => $social], ['rtc.property_use' => 'RESIDENTIAL', 'rtc.service_nbs_code' => '1.1002.10.00']);
    expect($result['status'])->toBe('VALID')->and($result['decision']['base']['taxable'])->toBe('87.66');
    $capped = realEstateB37WithSettlement(['social_reducer' => [...$social, 'months' => 2]], ['rtc.property_use' => 'RESIDENTIAL', 'rtc.service_nbs_code' => '1.1002.10.00']);
    expect($capped['decision']['base']['taxable'])->toBe('0.00')
        ->and($capped['decision']['treatment']['base_settlement']['lines'][2]['capped_at_base'])->toBeTrue();
    $unsourced = realEstateB37WithSettlement(['social_reducer' => ['monthly_value_per_property' => '600.00', 'properties' => 1, 'months' => 1]],
        ['rtc.property_use' => 'RESIDENTIAL', 'rtc.service_nbs_code' => '1.1002.10.00']);
    expect($unsourced['reason_codes'])->toBe(['RTC_REAL_ESTATE_SOCIAL_REDUCER_VALUE_SOURCE_REQUIRED']);
    expect(realEstateB37WithSettlement(['social_reducer' => $social])['reason_codes'])->toBe(['RTC_REAL_ESTATE_SOCIAL_REDUCER_ONLY_RESIDENTIAL']);
});

it('taxes administration and intermediation at 50% on the operation value', function (string $activity, string $canonical, string $nbs): void {
    $result = realEstateB37Evaluate(['rtc.real_estate_activity' => $activity, 'service.canonical_id' => $canonical,
        'rtc.operation_kind' => 'SERVICE', 'rtc.service_nbs_code' => $nbs, 'rtc.real_estate_settlement' => null]);
    expect($result['status'])->toBe('VALID')
        ->and($result['decision']['base']['taxable'])->toBe('1000.00')
        ->and($result['decision']['classification']['cclasstrib'])->toBe('200046')
        ->and($result['decision']['classification']['cindop'])->toBe('020301')
        ->and($result['decision']['cbs']['nominal_amount'])->toBe('4.50')
        ->and($result['decision']['ibs']['nominal_amount'])->toBe('0.50');
})->with([['ADMINISTRATION', 'CTRIBNAC_171201', '1.1001.11.00'], ['INTERMEDIATION', 'CTRIBNAC_100501', '1.1001.21.00']]);

it('refuses operations outside the regime conditions', function (array $overrides, string $reason): void {
    expect(realEstateB37Evaluate($overrides)['reason_codes'])->toContain($reason);
})->with([
    'individual below art. 251 thresholds' => [['rtc.real_estate_contributor_basis' => 'INDIVIDUAL_NOT_CONTRIBUTOR'], 'RTC_REAL_ESTATE_INDIVIDUAL_NOT_REGULAR_CONTRIBUTOR'],
    'short residential lease' => [['rtc.property_use' => 'RESIDENTIAL', 'rtc.lease_term_class' => 'UP_TO_90_DAYS_UNINTERRUPTED', 'rtc.service_nbs_code' => '1.1002.10.00'], 'RTC_REAL_ESTATE_SHORT_RESIDENTIAL_LEASE_FOLLOWS_HOSPITALITY'],
    'taxable event not at payment' => [['rtc.real_estate_payment_date' => '2026-09-20'], 'RTC_REAL_ESTATE_TAXABLE_EVENT_IS_PAYMENT'],
    'destination is not the property' => [['rtc.property_municipality_ibge' => '3550308'], 'RTC_REAL_ESTATE_DESTINATION_NOT_PROPERTY_LOCATION'],
    'missing registry' => [['rtc.property_registry_id' => null], 'RTC_REAL_ESTATE_PROOF_MISSING'],
    'lease claimed on administration code' => [['service.canonical_id' => 'CTRIBNAC_171201', 'rtc.operation_kind' => 'SERVICE', 'rtc.service_nbs_code' => '1.1001.11.00'], 'RTC_STANDARD_TREATMENT_CLASS_CONFLICT'],
]);

it('refuses lease settlements that do not prove the art. 255 base', function (array $changes, string $reason): void {
    expect(realEstateB37WithSettlement($changes)['reason_codes'])->toContain($reason);
})->with([
    'charged differs from gross' => [['amount_charged' => '900.00'], 'RTC_REAL_ESTATE_CHARGED_AMOUNT_CONFLICT'],
    'other property' => [['property_registry_id' => 'OTHER'], 'RTC_REAL_ESTATE_SETTLEMENT_BINDING_CONFLICT'],
    'line without evidence' => [['condominium_expenses' => [['id' => 'C', 'amount' => '1.00']]], 'RTC_REAL_ESTATE_LINE_INVALID:condominium_expenses'],
    'negative base' => [['condominium_expenses' => [['id' => 'C', 'amount' => '950.00', 'evidence_id' => 'E']]], 'RTC_REAL_ESTATE_NEGATIVE_BASE'],
]);

it('keeps the generic real-estate stop when the gate is absent (bundle B36)', function (): void {
    expect(fn () => realEstateB37Evaluate([], travelB36Knowledge()))->toThrow(RuntimeException::class, 'Unknown fact in scenario');
});
