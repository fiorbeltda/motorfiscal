<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\ProfessionalReductionQualifier;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/**
 * @param  array<string, mixed>  $overrides
 * @return array{0: array<string, mixed>, 1: mixed}
 */
function professionalCase(array $overrides = [], string $name = 'lawyer-reduced-2026'): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.01-4.json');
    $input = json_decode((string) file_get_contents($root.'/scenarios/consumption-phase4b-2026.10.01-4/'.$name.'.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($input['facts'][$key]);
        } else {
            $input['facts'][$key] = $value;
        }
    }

    return [$knowledge, $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($input)];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function professionalResult(array $overrides = [], string $name = 'lawyer-reduced-2026'): array
{
    [$knowledge, $scenario] = professionalCase($overrides, $name);
    $documents = $knowledge['documents'];
    $engine = new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog']));

    return $engine->evaluate($scenario->input->context->facts,
        $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
}

it('calculates documented professional reduction separately from collection relief', function (): void {
    $result = professionalResult();
    $decision = $result['decision'];
    expect($result['status'])->toBe('VALID')->and($decision['ibs']['state_rate_percent'])->toBe('0.07')
        ->and($decision['ibs']['nominal_amount'])->toBe('0.70')
        ->and($decision['cbs']['federal_rate_percent'])->toBe('0.63')
        ->and($decision['cbs']['nominal_amount'])->toBe('6.30')
        ->and($decision['cbs']['cash_due'])->toBe('0.00')
        ->and($decision['document']['issuer_fields']['IBSCBS.valores.trib.gIBSCBS.cClassTrib'])->toBe('200052')
        ->and($decision['document']['national_enrichment']['IBSCBS.valores.uf.pIBSUF'])->toBe('0.10')
        ->and($decision['document']['national_enrichment']['IBSCBS.valores.uf.pAliqEfetUF'])->toBe('0.07')
        ->and($decision['document']['national_enrichment']['IBSCBS.valores.fed.pRedAliqCBS'])->toBe('30.00')
        ->and($decision['treatment']['source_hash'])->toBe('dc87a43e7a4c293bc3c47817754286438db33a7c42472eb0400258e7707a2bde');
    $due = professionalResult(['rtc.accessory_obligations_fulfilled' => false]);
    expect($due['decision']['ibs']['cash_due'])->toBe('0.70')->and($due['decision']['cbs']['cash_due'])->toBe('6.30');
});

it('rejects each disqualifying professional society condition', function (string $key, bool $value): void {
    $result = professionalResult([$key => $value]);
    expect($result['status'])->toBe('INVALID')->and($result['reason_codes'])->toBe(['RTC_PROFESSIONAL_REDUCTION_NOT_ELIGIBLE']);
})->with([
    ['rtc.professional_council_supervised', false],
    ['rtc.professional_partners_qualified_and_supervised', false],
    ['rtc.professional_has_legal_entity_partner', true],
    ['rtc.professional_holds_legal_entity_participation', true],
    ['rtc.professional_has_unrelated_activities', true],
    ['rtc.professional_services_direct_by_partners', false],
]);

it('requires an exact missing professional proof instead of applying a presumptive reduction', function (): void {
    $result = professionalResult(['rtc.professional_structure_evidence_id' => null]);
    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['missing_facts'])->toBe(['rtc.professional_structure_evidence_id']);
    $conflict = professionalResult(['rtc.treatment_rule_id' => 'UNVERIFIED-RULE']);
    expect($conflict['reason_codes'])->toBe(['RTC_PROFESSIONAL_REDUCTION_RULE_CONFLICT']);
    $class = professionalResult(['rtc.cclasstrib' => '000001']);
    expect($class['status'])->toBe('INVALID')->and($class['reason_codes'])->toBe(['RTC_SUPPLIED_CLASSIFICATION_CONFLICT']);
});

it('applies the individual habilitation condition and the corporate physical education exception', function (): void {
    $individual = professionalResult([], 'lawyer-individual-reduced-2026');
    expect($individual['status'])->toBe('VALID');
    $unrelated = professionalResult(['rtc.professional_service_linked_habilitation' => false], 'lawyer-individual-reduced-2026');
    expect($unrelated['reason_codes'])->toBe(['RTC_PROFESSIONAL_REDUCTION_NOT_ELIGIBLE']);
    $overrides = ['rtc.professional_category' => 'PHYSICAL_EDUCATION'];
    foreach (['professional_partners_qualified_and_supervised', 'professional_has_legal_entity_partner',
        'professional_holds_legal_entity_participation', 'professional_has_unrelated_activities',
        'professional_services_direct_by_partners', 'professional_structure_evidence_id'] as $key) {
        $overrides['rtc.'.$key] = null;
    }
    [$knowledge, $scenario] = professionalCase($overrides);
    $qualified = (new ProfessionalReductionQualifier)->resolve($scenario->input->context->facts,
        $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt,
        $knowledge['documents']['rtc_code_catalog']['professional_reduction']);
    expect($qualified['status'])->toBe('RESOLVED')->and($qualified['physical_education_exception'])->toBeTrue();
});

it('keeps standard professional rates and refuses to invent the 2027 CBS rate', function (): void {
    $standard = professionalResult([], 'lawyer-standard-2026');
    expect($standard['status'])->toBe('VALID')->and($standard['decision']['ibs']['nominal_amount'])->toBe('1.00')
        ->and($standard['decision']['cbs']['nominal_amount'])->toBe('9.00');
    $future = professionalResult([], 'lawyer-reduced-rate-gap-2027');
    expect($future['status'])->toBe('ACTION_REQUIRED')->and(implode('|', $future['reason_codes']))->toContain('RATE');
});

it('honors publication validity revocation and supersession of the professional rule', function (string $condition): void {
    [$knowledge, $scenario] = professionalCase();
    $spec = $knowledge['documents']['rtc_code_catalog']['professional_reduction'];
    $knownAt = $scenario->input->context->knowledgeAt;
    if ($condition === 'publication') {
        $knownAt = (new DateTimeImmutable($spec['published_at']))->modify('-1 second');
    } elseif ($condition === 'validity') {
        $spec['valid_to'] = '2026-09-30';
    } else {
        $spec[$condition.'_at'] = $knownAt->format(DATE_ATOM);
    }
    $qualified = (new ProfessionalReductionQualifier)->resolve($scenario->input->context->facts,
        $scenario->input->context->competenceDate, $knownAt, $spec);
    expect($qualified['status'])->toBe('ACTION_REQUIRED')->and($qualified['reason'])->toBe('RTC_PROFESSIONAL_REDUCTION_RULE_NOT_AVAILABLE');
})->with(['publication', 'validity', 'revoked', 'superseded']);
