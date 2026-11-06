<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\HospitalityRegimeB35;
use App\Modules\Fiscal\ConsumptionReform\TravelAgencyRegimeB36;

require_once dirname(__DIR__, 3).'/scripts/agent_b/travel_agency_b36_fixture.php';

// B36 (4B.4.10): regime específico das agências de turismo, intermediação (LC 214, arts. 288-291).

it('quotes the regime literally from the hashed Planalto snapshot', function (): void {
    $path = resource_path('fiscal/sources/official/'.HospitalityRegimeB35::SOURCE_FILE);
    expect(hash_file('sha256', $path))->toBe(TravelAgencyRegimeB36::POLICY['source_hash']);
    $raw = (string) file_get_contents($path);
    if (! mb_check_encoding($raw, 'UTF-8')) {
        $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    }
    $text = (string) preg_replace('/\s+/u', ' ', html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', $raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    foreach (['a base de cálculo do IBS e da CBS é o valor da operação, deduzidos os valores repassados para os fornecedores intermediados pela agência com base no documento que subsidia a operação de agenciamento',
        'a alíquota é a mesma aplicável aos serviços de hotelaria, parques de diversão e parques temáticos',
        'Integram também a base de cálculo e sujeitam-se ao disposto neste artigo os demais valores, comissões e incentivos pagos por terceiros, em virtude da atuação da agência',
        'Fica permitida a apropriação, pelo adquirente, dos créditos de IBS e de CBS relativos ao serviço de intermediação prestado pela agência de turismo',
        'vedado o crédito dos valores que sejam deduzidos da base de cálculo'] as $excerpt) {
        expect(mb_substr_count($text, $excerpt))->toBe(1);
    }
});

it('taxes the agency on charged + third-party commissions - documented pass-through, at the hotelaria rate', function (): void {
    $result = travelB36Evaluate();
    expect($result['status'])->toBe('VALID');
    $decision = $result['decision'];
    expect($decision['base']['gross'])->toBe('1000.00')
        ->and($decision['base']['taxable'])->toBe('350.00')
        ->and($decision['treatment']['regime'])->toBe('TRAVEL_AGENCY_SPECIFIC')
        ->and($decision['treatment']['acquirer_credit'])->toBe('ALLOWED_ART290_ARTS_47_56')
        ->and($decision['treatment']['base_settlement']['lines'][1]['credit_by_agency'])->toBe('PROHIBITED_ART291')
        ->and($decision['classification']['cclasstrib'])->toBe('200051')
        ->and($decision['classification']['cindop'])->toBe('100301')
        ->and($decision['document']['issuer_fields']['IBSCBS.valores.trib.CST'])->toBe('200')
        ->and($decision['document']['national_enrichment']['IBSCBS.valores.vBC'])->toBe('350.00')
        // 2026 test rates reduced by 40%: CBS 0.54% and IBS 0.06% of 350.00.
        ->and($decision['cbs']['federal_rate_percent'])->toBe('0.54')
        ->and($decision['cbs']['nominal_amount'])->toBe('1.89')
        ->and($decision['ibs']['nominal_amount'])->toBe('0.21');
});

it('refuses settlements that do not prove the art. 289 base', function (array $changes, string $reason): void {
    expect(travelB36WithSettlement($changes)['reason_codes'])->toContain($reason);
})->with([
    'total differs from gross' => [['total_charged_to_user' => '999.00'], 'RTC_TRAVEL_AGENCY_TOTAL_CHARGED_CONFLICT'],
    'pass-through without supporting document' => [['supplier_pass_through' => [['id' => 'P1', 'supplier_id' => 'H', 'amount' => '700.00', 'evidence_id' => 'E']]], 'RTC_TRAVEL_AGENCY_LINE_PROOF_MISSING:supplier_pass_through'],
    'negative base' => [['supplier_pass_through' => [['id' => 'P1', 'supplier_id' => 'H', 'supporting_document_id' => 'D', 'amount' => '1100.00', 'evidence_id' => 'E']]], 'RTC_TRAVEL_AGENCY_NEGATIVE_BASE'],
    'duplicated line id' => [['third_party_commissions' => [['id' => 'P1', 'payer_id' => 'X', 'amount' => '1.00', 'evidence_id' => 'E']]], 'RTC_TRAVEL_AGENCY_LINE_INVALID:supplier_pass_through'],
    'other document' => [['document_id' => 'dps-other'], 'RTC_TRAVEL_AGENCY_SETTLEMENT_BINDING_CONFLICT'],
    'inexact money' => [['total_charged_to_user' => '1000'], 'RTC_TRAVEL_AGENCY_TOTAL_CHARGED_CONFLICT'],
]);

it('only materializes the intermediation of tourist services', function (): void {
    $result = travelB36Evaluate(['rtc.travel_agency_intermediation' => false]);
    expect($result['reason_codes'])->toBe(['RTC_TRAVEL_AGENCY_ONLY_INTERMEDIATION_MATERIALIZED']);
    expect(travelB36Evaluate(['rtc.travel_agency_settlement' => null])['reason_codes'])->toBe(['RTC_TRAVEL_AGENCY_PROOF_MISSING']);
});

it('does not combine the agency base with other base adjustments', function (): void {
    $result = travelB36Evaluate(['rtc.base_adjustments' => [['kind' => 'FREIGHT_CHARGED', 'amount' => '10.00', 'evidence_id' => 'F']]]);
    expect($result['status'])->not->toBe('VALID');
});

it('keeps tourist-operator NBS outside the rule until reviewed', function (): void {
    expect(travelB36Evaluate(['rtc.service_nbs_code' => '1.1805.40.00'])['reason_codes'])->toBe(['RTC_ENGINE_DECISION_RULE_NOT_PROVEN']);
});

it('forks bundle B35 extending only codes, facts, questions and controls', function (): void {
    $codes = travelB36Knowledge()['documents']['rtc_code_catalog'];
    $old = travelB36Knowledge('bundles/consumption-phase4b-2026.11.06-b35-draft1.json')['documents'];
    expect(array_values(array_diff(array_keys($codes), array_keys($old['rtc_code_catalog']))))->toBe([TravelAgencyRegimeB36::KEY])
        ->and(array_values(array_diff(array_keys($codes['engine_decision_rules']), array_keys($old['rtc_code_catalog']['engine_decision_rules']))))
        ->toBe(['RTC.CLASSIFICATION.TRAVEL_AGENCY.090201.ACQUIRER_DOMICILE.B36-1']);
    $facts = array_column(travelB36Knowledge()['documents']['fact_definitions'], null, 'key');
    expect($facts['rtc.specific_regime']['allowed_values'])->toBe(['HOSPITALITY', 'TRAVEL_AGENCY'])
        ->and(count($facts))->toBe(count($old['fact_definitions']) + 3);
});
