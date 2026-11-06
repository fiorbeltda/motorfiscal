<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\PlatformConsequencesB29;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

const B29_FACTS = [['rtc.platform_option', 'STRING'], ['rtc.supplier_registered_regime', 'STRING'],
    ['rtc.platform_option_supplier_consent_evidence_id', 'STRING'], ['rtc.platform_supplier_rules_available', 'BOOLEAN'],
    ['rtc.platform_cure_within_30_days', 'BOOLEAN'], ['rtc.platform_cure_document_id', 'STRING'],
    ['rtc.platform_cure_payment_id', 'STRING'], ['rtc.platform_split_information_provided', 'BOOLEAN'],
    ['rtc.supplier_exclusively_via_platform', 'BOOLEAN'], ['rtc.platform_registered_regular', 'BOOLEAN']];

/** SaaS scenario on bundle 09.28-4 (platform responsibility active) with the B29 gate only in memory. */
function b29Case(array $overrides, bool $enabled = true, mixed $policy = null): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.09.28-4.json');
    if ($enabled) {
        $knowledge['documents']['rtc_code_catalog'][PlatformConsequencesB29::KEY] = $policy ?? PlatformConsequencesB29::POLICY;
    }
    foreach (B29_FACTS as [$key, $type]) {
        $knowledge['documents']['fact_definitions'][] = ['key' => $key, 'type' => $type, 'material' => true];
    }
    $scenario = json_decode((string) file_get_contents($root.'/scenarios/consumption-phase4b/regular-saas-2026.json'), true, 512, JSON_THROW_ON_ERROR);
    $scenario['scenario_id'] = 'platform-b29';
    $scenario['knowledge_at'] = '2026-11-05T12:00:00-03:00';
    $scenario['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $scenario['catalog_versions'] = $knowledge['catalog_versions'];
    $scenario['facts'] = array_merge($scenario['facts'], ['rtc.platform_id' => 'platform-1',
        'rtc.platform_remote_intermediation' => true, 'rtc.platform_controlled_elements' => ['PAYMENT'],
        'rtc.platform_activity_exclusion' => 'NONE', 'rtc.platform_qualification_evidence_id' => 'platform-contract-1',
        'rtc.platform_reported_operation' => true, 'rtc.platform_report_evidence_id' => 'authority-report-1',
        'rtc.supplier_document_full_value' => true, 'rtc.supplier_document_evidence_id' => 'dps-issued-1',
        'rtc.platform_document_registered' => true, 'rtc.platform_document_evidence_id' => 'platform-document-register-1',
        'rtc.platform_option' => 'NONE', 'rtc.supplier_registered_regime' => 'REGULAR'], $overrides);
    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($scenario['facts'][$key]);
        }
    }
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($scenario);
    $documents = $knowledge['documents'];

    return (new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog'])))
        ->evaluate($loaded->input->context->facts, $loaded->input->context->competenceDate, $loaded->input->context->knowledgeAt);
}

it('keeps the supplier rules and liability when the platform is compliant (art. 22, II and §10, I)', function (): void {
    $result = b29Case([]);
    expect($result['status'])->toBe('VALID')->and($result['decision']['platform_b29'])->toMatchArray([
        'responsibility' => 'NOT_TRIGGERED', 'debt_calculation_rule' => 'SUPPLIER_REGIME_RULES', 'option' => 'NONE',
        'document_issuer' => 'SUPPLIER', 'payer' => 'SUPPLIER', 'differences_liability' => 'SUPPLIER',
        'split_available' => false, 'authenticated' => false]);
});

it('uses regular-regime rules for an unregistered domestic supplier (§10, II)', function (): void {
    expect(b29Case(['rtc.supplier_registered_regime' => 'NOT_REGISTERED'])['decision']['platform_b29']['debt_calculation_rule'])
        ->toBe('REGULAR_REGIME_RULES');
});

it('makes the platform solidary for a reporting omission while split is not available (§7 not met)', function (): void {
    $result = b29Case(['rtc.platform_reported_operation' => false]);
    expect($result['decision']['platform_b29'])->toMatchArray(['responsibility' => 'DOMESTIC_SUPPLIER_SOLIDARY',
        'differences_liability' => 'PLATFORM_SOLIDARY_WITH_SUPPLIER']);
});

it('requires the supplier consent for the §12 and §13 options and records who issues and pays', function (): void {
    expect(b29Case(['rtc.platform_option' => 'ISSUE_AND_PAY_ON_BEHALF'])['reason_codes'])->toBe(['RTC_PLATFORM_OPTION_CONSENT_REQUIRED']);
    $onBehalf = b29Case(['rtc.platform_option' => 'ISSUE_AND_PAY_ON_BEHALF',
        'rtc.platform_option_supplier_consent_evidence_id' => 'consent-1', 'rtc.platform_supplier_rules_available' => true]);
    expect($onBehalf['decision']['platform_b29'])->toMatchArray(['option' => 'ISSUE_AND_PAY_ON_BEHALF',
        'document_issuer' => 'PLATFORM_ON_BEHALF_OF_SUPPLIER', 'payer' => 'PLATFORM',
        'differences_liability' => 'SUPPLIER_KEEPS_DIFFERENCES', 'option_consent_evidence_id' => 'consent-1']);
    $substitute = b29Case(['rtc.platform_option' => 'SUBSTITUTE',
        'rtc.platform_option_supplier_consent_evidence_id' => 'consent-2', 'rtc.platform_supplier_rules_available' => true]);
    expect($substitute['decision']['platform_b29']['responsibility'])->toBe('PLATFORM_SUBSTITUTE_BY_OPTION')
        ->and($substitute['decision']['platform_b29']['source_articles'])->toContain('art. 22, §13');
});

it('never infers reference rates when the supplier rules are unavailable (§15)', function (): void {
    $result = b29Case(['rtc.platform_option' => 'SUBSTITUTE', 'rtc.platform_option_supplier_consent_evidence_id' => 'consent-2',
        'rtc.platform_supplier_rules_available' => false]);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason_codes'])->toBe(['RTC_PLATFORM_REFERENCE_RATES_NOT_PUBLISHED']);
});

it('moves accruals and penalty to the supplier when the platform cures within 30 days (§14)', function (): void {
    $missingDoc = ['rtc.supplier_document_full_value' => false, 'rtc.supplier_is_taxpayer' => true,
        'rtc.supplier_taxpayer_evidence_id' => 'taxpayer-1', 'rtc.supplier_registered' => true,
        'rtc.platform_supplier_rules_available' => true];
    expect(b29Case($missingDoc)['reason_codes'])->toBe(['RTC_PLATFORM_CURE_FACTS_MISSING'])
        ->and(b29Case($missingDoc + ['rtc.platform_cure_within_30_days' => true])['reason_codes'])->toBe(['RTC_PLATFORM_CURE_EVIDENCE_MISSING']);
    $cured = b29Case($missingDoc + ['rtc.platform_cure_within_30_days' => true, 'rtc.platform_cure_document_id' => 'nfse-cure-1',
        'rtc.platform_cure_payment_id' => 'pay-cure-1']);
    expect($cured['decision']['platform_b29'])->toMatchArray(['late_document_cure' => 'ACCRUALS_AND_PENALTY_ONLY_FROM_SUPPLIER',
        'responsibility' => 'DOMESTIC_SUPPLIER_SOLIDARY']);
});

it('qualifies the foreign-supplier registration waiver (§3)', function (): void {
    $result = b29Case(['rtc.supplier_country' => 'US', 'rtc.supplier_exclusively_via_platform' => true, 'rtc.platform_registered_regular' => true]);
    $block = array_column($result['trace'], null, 'stage')['PLATFORM_CONSEQUENCES_B29'];
    expect($block)->toMatchArray(['responsibility' => 'FOREIGN_SUPPLIER_SUBSTITUTIVE_SOLIDARY', 'supplier_registration_waived' => true,
        'debt_calculation_rule' => 'REGULAR_REGIME_RULES']);
});

it('refuses invalid options and a malformed policy; keeps the historical result without the gate', function (): void {
    expect(b29Case(['rtc.platform_option' => 'GUESS'])['reason_codes'])->toBe(['RTC_PLATFORM_OPTION_FACTS_INVALID'])
        ->and(b29Case([], true, ['version' => 'B29'])['reason_codes'])->toBe(['RTC_PLATFORM_B29_POLICY_INVALID'])
        ->and(b29Case([], false)['decision'])->not->toHaveKey('platform_b29');
});

it('installs the B29 gate and its ten facts with questions in a new bundle forked from B28', function (): void {
    $factory = new FiscalEngineFactory;
    $new = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.11.05-b29-draft1.json')['documents'];
    $old = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.11.05-b28-draft1.json')['documents'];
    expect(PlatformConsequencesB29::enabled($new['rtc_code_catalog']))->toBeTrue()
        ->and(array_values(array_diff(array_keys($new['rtc_code_catalog']), array_keys($old['rtc_code_catalog']))))->toBe([PlatformConsequencesB29::KEY]);
    $asked = array_column($new['questions'], 'answer_type', 'fact_key');
    foreach (B29_FACTS as [$key, $type]) {
        expect($new['fact_definitions'])->toContain(['key' => $key, 'type' => $type, 'material' => true])->and($asked[$key] ?? null)->toBe($type);
    }
});
