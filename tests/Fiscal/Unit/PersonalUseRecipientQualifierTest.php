<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function personalUseResult(array $overrides): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.09.28-11.json');
    $source = json_decode((string) file_get_contents($root.'/scenarios/consumption-phase4b-2026.09.28-8/employee-below-market-nominal-2026.json'),
        true, 512, JSON_THROW_ON_ERROR);
    $source['scenario_id'] = 'personal-use-recipient';
    $source['knowledge_at'] = '2026-09-28T15:16:00-03:00';
    $source['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $source['catalog_versions'] = $knowledge['catalog_versions'];
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

it('qualifies a documented shareholder below-market personal-use supply', function (): void {
    $result = personalUseResult([
        'rtc.recipient_relationship' => 'SHAREHOLDER',
        'rtc.recipient_relationship_evidence_id' => 'company-register-1',
    ]);
    $nominal = array_values(array_filter($result['trace'],
        static fn (array $row): bool => ($row['stage'] ?? null) === 'NON_ONEROUS_NOMINAL_CALCULATION'));

    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['reason_codes'])->toContain('RTC_NON_ONEROUS_DOCUMENT_NOT_MATERIALIZED')
        ->and($nominal[0]['ibs_state'])->toBe('1.00')
        ->and($nominal[0]['cbs'])->toBe('9.00');
});

it('qualifies a family member only with a covered anchor role and relationship evidence', function (): void {
    $facts = [
        'rtc.non_onerous_legal_basis' => 'LC214_ART5_I_B_2',
        'rtc.recipient_relationship' => 'SPOUSE_OR_PARTNER',
        'rtc.recipient_relationship_evidence_id' => 'civil-record-1',
        'rtc.recipient_anchor_role' => 'ADMINISTRATOR',
        'rtc.recipient_anchor_evidence_id' => 'company-register-1',
    ];
    $result = personalUseResult($facts);
    $incidence = array_values(array_filter($result['trace'],
        static fn (array $row): bool => ($row['stage'] ?? null) === 'NON_ONEROUS_INCIDENCE'));
    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['reason_codes'])->toContain('RTC_NON_ONEROUS_DOCUMENT_NOT_MATERIALIZED')
        ->and($incidence[0]['anchor_role'])->toBe('ADMINISTRATOR')
        ->and($incidence[0]['source_article'])->toContain('b, 2');

    $missing = personalUseResult(array_merge($facts, ['rtc.recipient_anchor_evidence_id' => null]));
    expect($missing['status'])->toBe('ACTION_REQUIRED')
        ->and($missing['missing_facts'])->toContain('rtc.recipient_anchor_evidence_id');
});

it('rejects conflicting personal-use roles and a price at market value', function (): void {
    $conflict = personalUseResult([
        'rtc.recipient_relationship' => 'SPOUSE_OR_PARTNER',
    ]);
    expect($conflict['status'])->toBe('INVALID')
        ->and($conflict['reason_codes'])->toContain('RTC_PERSONAL_USE_LEGAL_BASIS_RELATION_CONFLICT');

    $atMarket = personalUseResult([
        'rtc.recipient_relationship' => 'PARTNER',
        'rtc.gross_amount' => '1000.00', 'operation.amount' => '1000.00',
    ]);
    expect($atMarket['status'])->toBe('INVALID')
        ->and($atMarket['reason_codes'])->toContain('RTC_PERSONAL_USE_PRICE_NOT_BELOW_MARKET');

    $businessUse = personalUseResult([
        'rtc.recipient_relationship' => 'BOARD_OR_COMMITTEE_MEMBER',
        'rtc.business_use_predominant' => true,
    ]);
    expect($businessUse['status'])->toBe('ACTION_REQUIRED')
        ->and($businessUse['reason_codes'])->toContain('RTC_NON_ONEROUS_BUSINESS_USE_EXCEPTION');
});

it('requests missing recipient identity proof instead of declaring a conflict', function (): void {
    $result = personalUseResult([
        'rtc.recipient_relationship' => 'PARTNER',
        'customer.person_type' => null,
    ]);

    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['reason_codes'])->toContain('RTC_PERSONAL_USE_NATURAL_PERSON_UNPROVEN')
        ->and($result['missing_facts'])->toContain('customer.person_type');
});
