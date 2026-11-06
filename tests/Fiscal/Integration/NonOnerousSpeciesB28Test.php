<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\NonOnerousSpeciesB28;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

const B28_PERSONAL_ORIGIN = ['rtc.personal_use_supply_origin' => 'SELF_PRODUCED_OR_PROVIDED', 'rtc.personal_use_origin_evidence_id' => 'own-service-1'];

const B28_NEW_FACTS = [['rtc.market_value_method', 'STRING'], ['rtc.related_party_hypothesis', 'STRING'],
    ['rtc.related_party_evidence_id', 'STRING'], ['rtc.gift_bonus_evidence_id', 'STRING'],
    ['rtc.bonus_in_document_without_later_event', 'BOOLEAN']];

/** Employee SaaS grant scenario (bundle 09.28-14) re-based on a related-party or gift species. @return array<string, mixed> */
function b28Result(array $overrides, bool $enabled = true, mixed $policy = null): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.09.28-14.json');
    if ($enabled) {
        $knowledge['documents']['rtc_code_catalog'][NonOnerousSpeciesB28::KEY] = $policy ?? NonOnerousSpeciesB28::POLICY;
    }
    foreach (B28_NEW_FACTS as [$key, $type]) {
        $knowledge['documents']['fact_definitions'][] = ['key' => $key, 'type' => $type, 'material' => true];
    }
    $source = json_decode((string) file_get_contents($root.'/scenarios/consumption-phase4b-2026.09.28-8/employee-below-market-nominal-2026.json'),
        true, 512, JSON_THROW_ON_ERROR);
    $source['scenario_id'] = 'non-onerous-b28';
    $source['knowledge_at'] = '2026-11-05T12:00:00-03:00';
    $source['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $source['catalog_versions'] = $knowledge['catalog_versions'];
    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($source['facts'][$key]);
        } else {
            $source['facts'][$key] = $value;
        }
    }
    $documents = $knowledge['documents'];
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);

    return (new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog'])))
        ->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
}

/** @return array<string, mixed> */
function b28RelatedParty(array $extra = []): array
{
    return array_replace(['rtc.non_onerous_legal_basis' => 'LC214_ART5_IV', 'rtc.recipient_relationship' => null,
        'rtc.related_party_hypothesis' => 'ART5_PAR3_V', 'rtc.related_party_evidence_id' => 'cap-table-2026',
        'rtc.market_value_method' => 'COMPARABLE_UNRELATED_OPERATIONS'], $extra);
}

/** @return array<string, mixed> */
function b28Gift(array $extra = []): array
{
    return array_replace(['rtc.non_onerous_legal_basis' => 'LC214_ART5_II', 'rtc.recipient_relationship' => null,
        'rtc.consideration' => 'NON_ONEROUS', 'rtc.gross_amount' => '0.00', 'operation.amount' => null,
        'rtc.bonus_in_document_without_later_event' => false, 'rtc.gift_bonus_evidence_id' => 'campaign-terms-1',
        'rtc.market_value_method' => 'COMPARABLE_UNRELATED_OPERATIONS'], $extra);
}

/** @return array<string, mixed>|null */
function b28Stage(array $result, string $stage): ?array
{
    return array_column($result['trace'], null, 'stage')[$stage] ?? null;
}

it('documents the base scenario facts this suite relies on', function (): void {
    $result = b28Result(B28_PERSONAL_ORIGIN, false);
    expect($result['reason_codes'])->toContain('RTC_NON_ONEROUS_DOCUMENT_NOT_MATERIALIZED');
});

it('taxes a below-market supply to a related party on market value and builds the candidate document (art. 5, IV)', function (): void {
    $result = b28Result(b28RelatedParty());
    expect($result['status'])->toBe('VALID');
    $base = b28Stage($result, 'NON_ONEROUS_MARKET_BASE');
    $decision = $result['decision'];
    expect($base['source_article'])->toBe('LC214 art. 5º, IV e §§2º-6º; art. 12, §4º, IV')
        ->and($decision['base']['taxable'])->toBe($base['market_value_brl'])
        ->and($decision['classification']['cclasstrib'])->toBe('000001')
        ->and($decision['non_onerous_b28'])->toMatchArray(['species' => 'LC214_ART5_IV', 'base' => 'MARKET_VALUE',
            'ind_doacao' => null, 'review_status' => 'SANDBOX_REVIEW_REQUIRED']);
});

it('taxes a free supply to a related party on market value', function (): void {
    $result = b28Result(b28RelatedParty(['rtc.consideration' => 'NON_ONEROUS', 'rtc.gross_amount' => '0.00', 'operation.amount' => null]));
    expect($result['status'])->toBe('VALID')->and($result['decision']['non_onerous_b28']['species'])->toBe('LC214_ART5_IV');
});

it('refuses a related-party price at or above market value as art. 5, IV', function (): void {
    $market = b28Stage(b28Result(b28RelatedParty()), 'NON_ONEROUS_MARKET_BASE')['market_value_brl'];
    $result = b28Result(b28RelatedParty(['rtc.gross_amount' => $market, 'operation.amount' => null]));
    expect($result['status'])->toBe('INVALID')->and($result['reason_codes'])->toBe(['RTC_RELATED_PARTY_PRICE_NOT_BELOW_MARKET']);
});

it('taxes a gift or bonus of services on market value (art. 5, II)', function (): void {
    $result = b28Result(b28Gift());
    expect($result['status'])->toBe('VALID')->and($result['decision']['non_onerous_b28']['species'])->toBe('LC214_ART5_II')
        ->and(b28Stage($result, 'NON_ONEROUS_SPECIES_B28')['qualification'])->toBe(['kind' => 'GIFT_OR_BONUS', 'evidence_id' => 'campaign-terms-1']);
});

it('treats a bonus stated in the document without a later event as not a separate operation (§1, I; 410001)', function (): void {
    $result = b28Result(b28Gift(['rtc.bonus_in_document_without_later_event' => true]));
    expect($result['status'])->toBe('INVALID')->and($result['reason_codes'])->toBe(['RTC_BONUS_IN_DOCUMENT_IS_NOT_SEPARATE_OPERATION_410001']);
});

it('sends employees, partners and relatives to the specific art. 5, I route', function (): void {
    $result = b28Result(b28RelatedParty(['rtc.recipient_relationship' => 'EMPLOYEE']));
    expect($result['reason_codes'])->toBe(['RTC_NON_ONEROUS_B28_PERSONAL_ROUTE_REQUIRED']);
});

it('asks for the evidence and refuses unknown hypotheses or another market-value method', function (): void {
    $missing = b28Result(b28RelatedParty(['rtc.related_party_evidence_id' => null, 'rtc.market_value_method' => null]));
    expect($missing['status'])->toBe('ACTION_REQUIRED')
        ->and($missing['missing_facts'])->toContain('rtc.related_party_evidence_id', 'rtc.market_value_method');
    expect(b28Result(b28RelatedParty(['rtc.related_party_hypothesis' => 'FRIENDS']))['reason_codes'])->toBe(['RTC_RELATED_PARTY_HYPOTHESIS_UNKNOWN'])
        ->and(b28Result(b28RelatedParty(['rtc.market_value_method' => 'COST_PLUS']))['reason_codes'])->toBe(['RTC_MARKET_VALUE_METHOD_NOT_ART12_PAR4'])
        ->and(b28Result(b28Gift(['rtc.consideration' => 'ONEROUS']))['reason_codes'])->toBe(['RTC_GIFT_BONUS_CONSIDERATION_CONFLICT']);
});

it('materializes the candidate document for the existing personal-use species under the gate', function (): void {
    $result = b28Result(B28_PERSONAL_ORIGIN);
    expect($result['status'])->toBe('VALID')->and($result['decision']['non_onerous_b28']['base'])->toBe('MARKET_VALUE')
        ->and($result['decision']['non_onerous_b28']['review_status'])->toBe('SANDBOX_REVIEW_REQUIRED');
});

it('keeps the historical stop without the gate and rejects a malformed policy', function (): void {
    expect(b28Result(b28RelatedParty(), false)['reason_codes'])->not->toContain('RTC_NON_ONEROUS_B28_QUALIFIED')
        ->and(b28Result(B28_PERSONAL_ORIGIN, true, ['version' => 'B28'])['reason_codes'])->toBe(['RTC_NON_ONEROUS_B28_POLICY_INVALID']);
});

it('installs the B28 gate and its five facts with questions in a new bundle forked from B27', function (): void {
    $factory = new FiscalEngineFactory;
    $new = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.11.05-b28-draft1.json')['documents'];
    $old = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.11.05-b27-draft1.json')['documents'];
    expect(NonOnerousSpeciesB28::enabled($new['rtc_code_catalog']))->toBeTrue()
        ->and(array_values(array_diff(array_keys($new['rtc_code_catalog']), array_keys($old['rtc_code_catalog']))))->toBe([NonOnerousSpeciesB28::KEY]);
    $asked = array_column($new['questions'], 'answer_type', 'fact_key');
    foreach (B28_NEW_FACTS as [$key, $type]) {
        expect($new['fact_definitions'])->toContain(['key' => $key, 'type' => $type, 'material' => true])
            ->and($asked[$key] ?? null)->toBe($type);
    }
});
