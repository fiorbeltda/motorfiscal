<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\InstitutionalImmunityQualifier;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** @param array<string, mixed> $overrides
 * @return array{0: array<string, mixed>, 1: mixed}
 */
function institutionalImmunityCase(string $name = 'nonprofit-education', array $overrides = []): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.02-4.json');
    $input = json_decode((string) file_get_contents($root.'/scenarios/consumption-phase4b-2026.10.02-4/institutional-immunity-'.$name.'-2026.json'), true, 512, JSON_THROW_ON_ERROR);
    $input['facts'] = array_replace($input['facts'], $overrides);

    return [$knowledge, $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input)];
}

/** @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function institutionalImmunityResult(string $name = 'nonprofit-education', array $overrides = []): array
{
    [$knowledge, $scenario] = institutionalImmunityCase($name, $overrides);
    $documents = $knowledge['documents'];

    return (new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog'])))
        ->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
}

it('qualifies institutional supplier immunity with legal class without a taxable rate', function (string $name, string $cclass): void {
    $result = institutionalImmunityResult($name);
    expect($result['status'])->toBe('VALID')->and($result['reason_codes'])->toBe(['RTC_INSTITUTIONAL_IMMUNITY'])
        ->and($result['decision']['classification']['cclasstrib'])->toBe($cclass)
        ->and($result['decision']['base']['taxable'])->toBe('0.00')
        ->and($result['decision']['ibs']['status'])->toBe('IMMUNE')
        ->and($result['decision']['ibs']['state_rate_percent'])->toBeNull()
        ->and($result['decision']['cbs']['nominal_amount'])->toBe('0.00')
        ->and($result['decision']['document']['national_enrichment'])->toBe([])
        ->and($result['decision']['document']['issuer_field_validation']['status'])->toBe('VALID');
})->with([['nonprofit-education', '410007'], ['religious-entity', '410006']]);

it('refuses immunity based on a different party document period or unmet conditions', function (string $name, string $reason): void {
    $result = institutionalImmunityResult($name);
    expect($result['status'])->toBe('INVALID')->and($result['reason_codes'])->toBe([$reason]);
})->with([
    ['wrong-supplier', 'RTC_INSTITUTIONAL_IMMUNITY_SUPPLIER_OR_DOCUMENT_CONFLICT'],
    ['wrong-document', 'RTC_INSTITUTIONAL_IMMUNITY_SUPPLIER_OR_DOCUMENT_CONFLICT'],
    ['future-observation', 'RTC_INSTITUTIONAL_IMMUNITY_PERIOD_CONFLICT'],
    ['distribution', 'RTC_INSTITUTIONAL_IMMUNITY_CONDITION_NOT_MET:no_asset_or_income_distribution'],
    ['resources-abroad', 'RTC_INSTITUTIONAL_IMMUNITY_CONDITION_NOT_MET:resources_applied_in_brazil'],
    ['bookkeeping', 'RTC_INSTITUTIONAL_IMMUNITY_CONDITION_NOT_MET:formal_accurate_bookkeeping'],
    ['unrelated-service', 'RTC_INSTITUTIONAL_IMMUNITY_CONDITION_NOT_MET:service_directly_related_to_statutory_objectives'],
    ['suspended-benefit', 'RTC_INSTITUTIONAL_IMMUNITY_CONDITION_NOT_MET:benefit_not_suspended'],
    ['for-profit', 'RTC_INSTITUTIONAL_IMMUNITY_CONDITION_NOT_MET:nonprofit'],
    ['employers-union', 'RTC_INSTITUTIONAL_IMMUNITY_CATEGORY_NOT_ELIGIBLE'],
    ['wrong-rule', 'RTC_INSTITUTIONAL_IMMUNITY_RULE_CONFLICT'],
    ['not-religious', 'RTC_INSTITUTIONAL_IMMUNITY_CONDITION_NOT_MET:professes_faith_and_practices_religion'],
]);

it('requires complete current evidence for institutional immunity', function (string $name, string $reason): void {
    $result = institutionalImmunityResult($name);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason_codes'])->toBe([$reason]);
})->with([
    ['missing-proof', 'RTC_INSTITUTIONAL_IMMUNITY_PROOF_MISSING'],
    ['expired-proof', 'RTC_INSTITUTIONAL_IMMUNITY_PROOF_OUTSIDE_PERIOD'],
    ['missing-bookkeeping-evidence', 'RTC_INSTITUTIONAL_IMMUNITY_EVIDENCE_MISSING:bookkeeping_evidence_id'],
    ['unknown-condition', 'RTC_INSTITUTIONAL_IMMUNITY_CONDITION_UNPROVEN:resources_applied_in_brazil'],
]);

it('does not grant supplier immunity merely because the buyer is an immune institution', function (): void {
    $result = institutionalImmunityResult('nonprofit-education', ['customer.person_type' => 'PJ_IMMUNE']);
    expect($result['status'])->toBe('VALID');
    [, $scenario] = institutionalImmunityCase();
    $proof = $scenario->input->context->facts->value('rtc.institutional_immunity_proof');
    $proof[0]['supplier_id'] = $scenario->input->context->facts->value('rtc.acquirer_id');
    $result = institutionalImmunityResult('nonprofit-education', ['customer.person_type' => 'PJ_IMMUNE', 'rtc.institutional_immunity_proof' => $proof]);
    expect($result['status'])->toBe('INVALID');
});

it('selects observed and unrevoked immunity rules and requires religious assistance conditions', function (): void {
    [$knowledge, $scenario] = institutionalImmunityCase();
    $context = $scenario->input->context;
    $rule = $knowledge['documents']['rtc_code_catalog']['institutional_immunity'];
    $qualifier = new InstitutionalImmunityQualifier;
    expect($qualifier->resolve($context->facts, LocalDate::fromString('2026-10-01'), new DateTimeImmutable('2026-09-30'), $rule)['status'])->toBe('ACTION_REQUIRED');
    $rule['revoked_at'] = $rule['published_at'];
    expect($qualifier->resolve($context->facts, $context->competenceDate, $context->knowledgeAt, $rule)['status'])->toBe('ACTION_REQUIRED');
    [, $religious] = institutionalImmunityCase('religious-entity');
    $proof = $religious->input->context->facts->value('rtc.institutional_immunity_proof');
    $proof[0]['category'] = 'RELIGIOUS_ASSISTANCE';
    foreach (['linked_and_maintained_by_religious_entity', 'social_assistance_supply', 'no_beneficiary_discrimination', 'no_beneficiary_requirements'] as $key) {
        $proof[0][$key] = true;
    }
    $proof[0]['religious_link_evidence_id'] = 'FIXTURE.RELIGIOUS.LINK';
    $proof[0]['beneficiary_policy_evidence_id'] = 'FIXTURE.BENEFICIARY.POLICY';
    $proof[0]['no_beneficiary_discrimination'] = false;
    expect(institutionalImmunityResult('religious-entity', ['rtc.institutional_immunity_proof' => $proof])['reason_codes'])
        ->toBe(['RTC_INSTITUTIONAL_IMMUNITY_CONDITION_NOT_MET:no_beneficiary_discrimination']);
});

it('does not require an unknown future tax rate for immunity but still checks the document layout', function (): void {
    $result = institutionalImmunityResult('nonprofit-education', ['rtc.operation_date' => '2027-01-01']);
    expect($result['reason_codes'])->toBe(['RTC_INSTITUTIONAL_IMMUNITY_PROOF_OUTSIDE_PERIOD']);
    [, $scenario] = institutionalImmunityCase();
    $proof = $scenario->input->context->facts->value('rtc.institutional_immunity_proof');
    $proof[0]['valid_to'] = '2027-12-31';
    $result = institutionalImmunityResult('nonprofit-education', ['rtc.operation_date' => '2027-01-01', 'rtc.institutional_immunity_proof' => $proof]);
    expect($result['reason_codes'])->toBe(['RTC_DOCUMENT_LAYOUT_NOT_PROVEN'])
        ->and(array_column($result['trace'], 'stage'))->toContain('IMMUNE_SUPPLY');
});
