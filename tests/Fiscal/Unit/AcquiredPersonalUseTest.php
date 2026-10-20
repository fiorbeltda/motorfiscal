<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function acquiredPersonalUseResult(array $overrides): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.09.28-14.json');
    $source = json_decode((string) file_get_contents($root.'/scenarios/consumption-phase4b-2026.09.28-8/employee-below-market-nominal-2026.json'),
        true, 512, JSON_THROW_ON_ERROR);
    $source['scenario_id'] = 'acquired-personal-use';
    $source['knowledge_at'] = '2026-09-28T17:31:00-03:00';
    $source['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $source['catalog_versions'] = $knowledge['catalog_versions'];
    $source['facts'] += [
        'rtc.personal_use_supply_origin' => 'ACQUIRED_FROM_THIRD_PARTY',
        'rtc.personal_use_origin_evidence_id' => 'supplier-invoice-1',
        'rtc.personal_use_input_credit_eligible' => true,
        'rtc.personal_use_input_credit_evidence_id' => 'supplier-invoice-credit-1',
    ];
    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($source['facts'][$key]);
        } else {
            $source['facts'][$key] = $value;
        }
    }
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $documents = $knowledge['documents'];
    $engine = new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog']));

    return $engine->evaluate($scenario->input->context->facts,
        $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
}

it('qualifies an acquired service for employee personal use only with credit eligibility proof', function (): void {
    $base = ['rtc.non_onerous_legal_basis' => 'LC214_ART5_I_A_3'];
    $qualified = acquiredPersonalUseResult($base);
    $credit = array_values(array_filter($qualified['trace'],
        static fn (array $row): bool => ($row['stage'] ?? null) === 'PERSONAL_USE_INPUT_CREDIT'));
    expect($qualified['status'])->toBe('ACTION_REQUIRED')
        ->and($qualified['reason_codes'])->toContain('RTC_NON_ONEROUS_DOCUMENT_NOT_MATERIALIZED')
        ->and($credit[0]['eligibility_evidence_id'])->toBe('supplier-invoice-credit-1');

    $missing = acquiredPersonalUseResult($base + ['rtc.personal_use_input_credit_evidence_id' => null]);
    expect($missing['status'])->toBe('ACTION_REQUIRED')
        ->and($missing['missing_facts'])->toContain('rtc.personal_use_input_credit_evidence_id');

    $ineligible = acquiredPersonalUseResult($base + ['rtc.personal_use_input_credit_eligible' => false]);
    expect($ineligible['status'])->toBe('INVALID')
        ->and($ineligible['reason_codes'])->toContain('RTC_PERSONAL_USE_INPUT_CREDIT_BASIS_CONFLICT');
});

it('separates acquired partner, taxpayer and family relationships', function (): void {
    $partner = acquiredPersonalUseResult([
        'rtc.non_onerous_legal_basis' => 'LC214_ART5_I_A_2',
        'rtc.recipient_relationship' => 'PARTNER',
    ]);
    expect($partner['reason_codes'])->toContain('RTC_NON_ONEROUS_DOCUMENT_NOT_MATERIALIZED');

    $self = acquiredPersonalUseResult([
        'rtc.non_onerous_legal_basis' => 'LC214_ART5_I_A_1',
        'rtc.recipient_relationship' => 'SELF',
        'provider.person_type' => 'INDIVIDUAL',
        'rtc.supplier_id' => '22222222222',
    ]);
    expect($self['reason_codes'])->toContain('RTC_NON_ONEROUS_DOCUMENT_NOT_MATERIALIZED');

    $family = acquiredPersonalUseResult([
        'rtc.non_onerous_legal_basis' => 'LC214_ART5_I_A_4',
        'rtc.recipient_relationship' => 'SPOUSE_OR_PARTNER',
        'rtc.recipient_anchor_role' => 'SELF',
        'rtc.recipient_anchor_evidence_id' => 'taxpayer-family-register-1',
        'provider.person_type' => 'INDIVIDUAL',
    ]);
    expect($family['reason_codes'])->toContain('RTC_NON_ONEROUS_DOCUMENT_NOT_MATERIALIZED');

    $wrongOrigin = acquiredPersonalUseResult([
        'rtc.non_onerous_legal_basis' => 'LC214_ART5_I_A_3',
        'rtc.personal_use_supply_origin' => 'SELF_PRODUCED_OR_PROVIDED',
    ]);
    expect($wrongOrigin['status'])->toBe('INVALID')
        ->and($wrongOrigin['reason_codes'])->toContain('RTC_PERSONAL_USE_LEGAL_BASIS_ORIGIN_CONFLICT');
});
