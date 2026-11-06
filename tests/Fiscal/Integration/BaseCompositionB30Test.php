<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\BaseCompositionB30;
use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** SaaS scenario (bundle 09.28-4) with the B30 gate only in memory. @param list<array<string, mixed>> $adjustments */
function b30Case(array $adjustments, bool $enabled = true, mixed $policy = null, array $facts = []): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.09.28-4.json');
    if ($enabled) {
        $knowledge['documents']['rtc_code_catalog'][BaseCompositionB30::KEY] = $policy ?? BaseCompositionB30::POLICY;
    }
    $scenario = json_decode((string) file_get_contents($root.'/scenarios/consumption-phase4b/regular-saas-2026.json'), true, 512, JSON_THROW_ON_ERROR);
    $scenario['scenario_id'] = 'base-b30';
    $scenario['knowledge_at'] = '2026-11-05T12:00:00-03:00';
    $scenario['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $scenario['catalog_versions'] = $knowledge['catalog_versions'];
    $scenario['facts'] = array_merge($scenario['facts'], ['rtc.base_adjustments' => $adjustments], $facts);
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($scenario);
    $documents = $knowledge['documents'];

    return (new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog'])))
        ->evaluate($loaded->input->context->facts, $loaded->input->context->competenceDate, $loaded->input->context->knowledgeAt);
}

/** @return array<string, mixed> */
function b30Adj(string $kind, string $amount, array $extra = []): array
{
    return ['kind' => $kind, 'amount' => $amount, 'evidence_id' => 'ev-'.strtolower($kind)] + $extra;
}

it('includes each art. 12 §1 amount in the base with its legal reference', function (array $adjustment, string $base, string $article): void {
    $result = b30Case([$adjustment]);
    expect($result['status'])->toBe('VALID')->and($result['decision']['base']['taxable'])->toBe($base);
    $stage = array_column($result['trace'], null, 'stage')['BASE_COMPOSITION_B30'];
    expect($stage['typed_adjustments'][0]['source_article'])->toBe($article);
})->with([
    'price adjustment (I)' => [b30Adj('PRICE_ADJUSTMENT_INCREASE', '50.00'), '1050.00', 'LC214 art. 12, §1º, I'],
    'interest and fines (II)' => [b30Adj('INTEREST_FINE_CHARGE', '12.34'), '1012.34', 'LC214 art. 12, §1º, II'],
    'conditional discount deducted from gross (III)' => [b30Adj('CONDITIONAL_DISCOUNT', '100.00', ['already_deducted_from_gross' => true]), '1100.00', 'LC214 art. 12, §1º, III'],
    'conditional discount not deducted (III)' => [b30Adj('CONDITIONAL_DISCOUNT', '100.00', ['already_deducted_from_gross' => false]), '1000.00', 'LC214 art. 12, §1º, III'],
    'freight by supplier (IV)' => [b30Adj('FREIGHT_CHARGED', '30.00', ['transport_responsibility' => 'SUPPLIER']), '1030.00', 'LC214 art. 12, §1º, IV'],
    'public price (V)' => [b30Adj('TAXES_AND_PUBLIC_PRICES', '5.00', ['tax' => 'TAXA_DE_FISCALIZACAO']), '1005.00', 'LC214 art. 12, §1º, V'],
    'insurance (VI)' => [b30Adj('OTHER_CHARGED_AMOUNT', '20.00', ['description' => 'seguro']), '1020.00', 'LC214 art. 12, §1º, VI'],
]);

it('excludes the art. 149-A contribution (§2, VI) and combines with the accepted exclusions', function (): void {
    $result = b30Case([b30Adj('COSIP_EXCLUSION', '10.00'), b30Adj('INTEREST_FINE_CHARGE', '40.00'),
        b30Adj('UNCONDITIONAL_DISCOUNT', '100.00', ['document_id' => 'dps-1', 'depends_on_later_event' => false])]);
    expect($result['status'])->toBe('VALID')->and($result['decision']['base']['taxable'])->toBe('930.00');
});

it('refuses inclusions that the law does not allow', function (array $adjustment, string $reason): void {
    expect(b30Case([$adjustment])['reason_codes'])->toBe([$reason]);
})->with([
    'freight by the acquirer' => [b30Adj('FREIGHT_CHARGED', '30.00', ['transport_responsibility' => 'ACQUIRER_THIRD_PARTY']), 'RTC_FREIGHT_NOT_SUPPLIER_TRANSPORT'],
    'ISS as an included tax' => [b30Adj('TAXES_AND_PUBLIC_PRICES', '5.00', ['tax' => 'ISS']), 'RTC_INCLUDED_TAX_IS_EXCLUDED_OR_UNNAMED'],
    'IBS as an included tax' => [b30Adj('TAXES_AND_PUBLIC_PRICES', '5.00', ['tax' => 'ibs']), 'RTC_INCLUDED_TAX_IS_EXCLUDED_OR_UNNAMED'],
    'undescribed other amount' => [b30Adj('OTHER_CHARGED_AMOUNT', '5.00'), 'RTC_OTHER_CHARGED_AMOUNT_UNDESCRIBED'],
    'conditional discount without gross state' => [b30Adj('CONDITIONAL_DISCOUNT', '5.00'), 'RTC_CONDITIONAL_DISCOUNT_GROSS_STATE_UNPROVEN'],
]);

it('treats a loyalty discount as unconditional only when the supplier grants it non-onerously (§3)', function (): void {
    $doc = ['document_id' => 'dps-1', 'depends_on_later_event' => false, 'loyalty_program' => true];
    expect(b30Case([b30Adj('UNCONDITIONAL_DISCOUNT', '10.00', $doc)])['reason_codes'])->toBe(['RTC_LOYALTY_DISCOUNT_NOT_SUPPLIER_NON_ONEROUS'])
        ->and(b30Case([b30Adj('UNCONDITIONAL_DISCOUNT', '10.00', $doc + ['loyalty_granted_non_onerously_by_supplier' => true])])['decision']['base']['taxable'])
        ->toBe('990.00');
});

it('refuses a negative base after exclusions', function (): void {
    expect(b30Case([b30Adj('COSIP_EXCLUSION', '1000.01')])['reason_codes'])->toBe(['RTC_NEGATIVE_BASE_AFTER_ADJUSTMENTS']);
});

it('keeps unknown kinds refused and the historical behaviour without the gate', function (): void {
    expect(b30Case([b30Adj('INTEREST_FINE_CHARGE', '1.00')], false)['reason_codes'])->toBe(['RTC_ADJUSTMENT_KIND_UNKNOWN'])
        ->and(b30Case([], false)['decision']['base']['taxable'])->toBe('1000.00')
        ->and(b30Case([], true, ['version' => 'B30'])['reason_codes'])->toBe(['RTC_BASE_COMPOSITION_B30_POLICY_INVALID']);
});

it('installs the B30 gate in a new bundle forked from B29 without new facts', function (): void {
    $factory = new FiscalEngineFactory;
    $new = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.11.05-b30-draft1.json')['documents'];
    $old = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.11.05-b29-draft1.json')['documents'];
    expect(BaseCompositionB30::enabled($new['rtc_code_catalog']))->toBeTrue()
        ->and(array_values(array_diff(array_keys($new['rtc_code_catalog']), array_keys($old['rtc_code_catalog']))))->toBe([BaseCompositionB30::KEY])
        ->and($new['fact_definitions'])->toBe($old['fact_definitions']);
});
