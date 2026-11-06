<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\AcquirerCreditProhibitionCatalogB13;
use App\Modules\Fiscal\ConsumptionReform\HospitalityRegimeB35;

require_once dirname(__DIR__, 3).'/scripts/agent_b/hospitality_b35_fixture.php';

// B35 (4B.4.10): regime específico de hotelaria, parques de diversão e parques temáticos (LC 214, arts. 277-283).

it('quotes the regime literally from the hashed Planalto snapshot', function (): void {
    $path = resource_path('fiscal/sources/official/'.HospitalityRegimeB35::SOURCE_FILE);
    expect(hash_file('sha256', $path))->toBe(HospitalityRegimeB35::SOURCE_SHA256);
    $raw = (string) file_get_contents($path);
    if (! mb_check_encoding($raw, 'UTF-8')) {
        $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    }
    $text = preg_replace('/\s+/u', ' ', html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', $raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    foreach (['Art. 281. As alíquotas do IBS e da CBS relativas às operações de que trata este Capítulo ficam reduzidas em 40% (quarenta por cento).',
        'Art. 280. A base de cálculo do IBS e da CBS é o valor da operação com serviços de hotelaria, parques de diversão e parques temáticos.',
        'observarão as regras relativas ao regime específico de bares e restaurantes.',
        'Fica vedada a apropriação de créditos de IBS e de CBS pelo adquirente dos serviços de hotelaria, parques de diversão e parques temáticos.',
        'considera-se serviço de hotelaria o fornecimento de alojamento temporário'] as $excerpt) {
        expect(mb_substr_count((string) $text, $excerpt))->toBe(1);
    }
    expect(AcquirerCreditProhibitionCatalogB13::ENTRIES['HOTEL_AMUSEMENT_PARK'][0])->toBe('LC214 art. 283');
});

it('calculates hotel lodging with the 40% reduction and cClassTrib 200048', function (): void {
    $result = hospitalityB35Evaluate();
    expect($result['status'])->toBe('VALID');
    $decision = $result['decision'];
    expect($decision['treatment']['regime'])->toBe('HOSPITALITY_SPECIFIC')
        ->and($decision['treatment']['rate_reduction_percent'])->toBe('40.00')
        ->and($decision['treatment']['acquirer_credit'])->toBe('PROHIBITED_ART283')
        ->and($decision['classification']['cclasstrib'])->toBe('200048')
        ->and($decision['classification']['cindop'])->toBe('030101')
        ->and($decision['classification']['review_status'])->toBe('SANDBOX_REVIEW_REQUIRED')
        ->and($decision['document']['issuer_fields']['IBSCBS.valores.trib.CST'])->toBe('200')
        ->and($decision['document']['issuer_fields']['IBSCBS.valores.trib.cClassTrib'])->toBe('200048')
        ->and($decision['document']['national_enrichment']['IBSCBS.valores.fed.pRedAliqCBS'])->toBe('40.00')
        // 2026 test rates: CBS 0.9% and IBS 0.1%, reduced by 40% -> 0.54% and 0.06% of 1000.00.
        ->and($decision['cbs']['federal_rate_percent'])->toBe('0.54')
        ->and($decision['cbs']['nominal_amount'])->toBe('5.40')
        ->and($decision['ibs']['nominal_amount'])->toBe('0.60');
});

it('stops the integrated run at ISS: no municipal parameter is invented for lodging', function (): void {
    $result = hospitalityB35Evaluate([], null, true);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason_codes'])->toBe(['MUNICIPAL_PARAMETERS_NOT_COVERED']);
});

it('qualifies amusement and theme parks under art. 279', function (string $activity, ?string $theme): void {
    $result = hospitalityB35Evaluate(['service.canonical_id' => 'CTRIBNAC_120501', 'service.ctribnac_code' => '120501',
        'rtc.hospitality_activity' => $activity, 'rtc.hotel_unit_kind' => null, 'rtc.hotel_temporary_lodging' => null,
        'rtc.park_presential_attractions_essential' => true, 'rtc.theme_park_theme' => $theme,
        'rtc.service_nbs_code' => $activity === 'THEME_PARK' ? '1.2507.10.00' : '1.2507.90.00']);
    expect($result['status'])->toBe('VALID')
        ->and($result['decision']['treatment']['activity'])->toBe($activity)
        ->and($result['decision']['treatment']['qualification']['theme'])->toBe($theme)
        ->and($result['decision']['cbs']['nominal_amount'])->toBe('5.40');
})->with([['AMUSEMENT_PARK', null], ['THEME_PARK', 'ENVIRONMENTAL']]);

it('refuses to fold food and beverages into the hospitality base (art. 280, par. único)', function (): void {
    $result = hospitalityB35Evaluate(['rtc.hospitality_food_beverage_in_price' => true]);
    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['reason_codes'])->toBe(['RTC_HOSPITALITY_FOOD_FOLLOWS_RESTAURANT_REGIME']);
});

it('refuses activities outside the legal definitions', function (array $overrides, string $reason): void {
    $result = hospitalityB35Evaluate($overrides);
    expect($result['reason_codes'])->toContain($reason);
})->with([
    'not temporary lodging' => [['rtc.hotel_temporary_lodging' => false], 'RTC_HOTEL_DEFINITION_NOT_MET'],
    'park without presential attractions' => [['rtc.hospitality_activity' => 'AMUSEMENT_PARK', 'rtc.park_presential_attractions_essential' => false], 'RTC_PARK_DEFINITION_NOT_MET'],
    'theme park without theme' => [['rtc.hospitality_activity' => 'THEME_PARK', 'rtc.park_presential_attractions_essential' => true], 'RTC_HOSPITALITY_PROOF_MISSING'],
    'wrong rule id' => [['rtc.treatment_rule_id' => 'RTC.TREATMENT.OTHER'], 'RTC_HOSPITALITY_RULE_CONFLICT'],
    'missing scope proof' => [['rtc.hospitality_scope_evidence_id' => null], 'RTC_HOSPITALITY_PROOF_MISSING'],
    'lodging NBS outside 1.0303' => [['rtc.service_nbs_code' => '1.0304.10.00'], 'RTC_ENGINE_DECISION_RULE_NOT_PROVEN'],
]);

it('keeps the B34 bundle unaware of the regime', function (): void {
    $knowledge = hospitalityB35Knowledge('bundles/consumption-phase4b-2026.11.06-b34-draft1.json');
    expect(fn () => hospitalityB35Evaluate([], $knowledge))->toThrow(RuntimeException::class, 'Unknown fact in scenario');
});

it('refuses an altered regime policy', function (): void {
    $knowledge = hospitalityB35Knowledge();
    $knowledge['documents']['rtc_code_catalog'][HospitalityRegimeB35::KEY]['rate_reduction_percent'] = '60.00';
    $result = hospitalityB35Evaluate([], refresh156($knowledge));
    expect($result['reason_codes'])->toBe(['RTC_HOSPITALITY_RULE_NOT_AVAILABLE']);
});

it('forks bundle B34 changing only the code catalog, facts, questions and production controls', function (): void {
    $read = fn (string $p): array => json_decode((string) file_get_contents(resource_path('fiscal/'.$p)), true, 512, JSON_THROW_ON_ERROR);
    $new = $read(B35_BUNDLE);
    $old = $read('bundles/consumption-phase4b-2026.11.06-b34-draft1.json');
    expect($new['predecessor_bundle_id'])->toBe($old['bundle_id'])
        ->and(array_keys(array_diff_assoc($new['content_hashes'], $old['content_hashes'])))
        ->toEqualCanonicalizing(['rtc_code_catalog', 'fact_definitions', 'questions']);
    $codes = hospitalityB35Knowledge()['documents']['rtc_code_catalog'];
    $oldCodes = hospitalityB35Knowledge('bundles/consumption-phase4b-2026.11.06-b34-draft1.json')['documents']['rtc_code_catalog'];
    expect(array_values(array_diff(array_keys($codes), array_keys($oldCodes))))->toBe([HospitalityRegimeB35::KEY])
        ->and(array_values(array_diff(array_keys($codes['engine_decision_rules']), array_keys($oldCodes['engine_decision_rules']))))->toHaveCount(5);
    foreach ($oldCodes as $key => $value) {
        if (! in_array($key, ['version', 'engine_decision_rules'], true)) {
            expect(json_encode($codes[$key], JSON_THROW_ON_ERROR))->toBe(json_encode($value, JSON_THROW_ON_ERROR));
        }
    }
});
