<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\AnnexServiceReductionQualifier;
use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/**
 * @param  array<string, mixed>  $overrides
 * @return array{0: array<string, mixed>, 1: mixed}
 */
function annexServiceCase(string $name = 'education-presential-2026', array $overrides = []): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.10.01-6.json');
    $input = json_decode((string) file_get_contents($root.'/scenarios/consumption-phase4b-2026.10.01-6/'.$name.'.json'), true, 512, JSON_THROW_ON_ERROR);
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
function annexServiceResult(string $name = 'education-presential-2026', array $overrides = []): array
{
    [$knowledge, $scenario] = annexServiceCase($name, $overrides);
    $documents = $knowledge['documents'];
    $engine = new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog']));

    return $engine->evaluate($scenario->input->context->facts, $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
}

it('applies reduced education and health rates with an evidenced statutory item', function (string $name, string $class): void {
    $result = annexServiceResult($name);
    $decision = $result['decision'];
    expect($result['status'])->toBe('VALID')->and($decision['ibs']['state_rate_percent'])->toBe('0.04')
        ->and($decision['ibs']['nominal_amount'])->toBe('0.40')->and($decision['cbs']['federal_rate_percent'])->toBe('0.36')
        ->and($decision['cbs']['nominal_amount'])->toBe('3.60')->and($decision['classification']['cclasstrib'])->toBe($class)
        ->and($decision['classification']['cindop'])->toBe('030101')
        ->and($decision['document']['national_enrichment']['IBSCBS.valores.fed.pRedAliqCBS'])->toBe('60.00');
})->with([['education-presential-2026', '200028'], ['health-presential-2026', '200029']]);

it('distinguishes physical performance from remote acquirer domicile and offsite performance', function (): void {
    $remote = annexServiceResult('education-remote-2026');
    expect($remote['status'])->toBe('VALID')->and($remote['decision']['classification']['cindop'])->toBe('100301')
        ->and($remote['decision']['destination']['municipality_ibge'])->toBe('3509502');
    $offsite = annexServiceResult('education-presential-2026', ['rtc.presence_at_supplier_establishment' => false]);
    expect($offsite['decision']['classification']['cindop'])->toBe('030102');
    $wrong = annexServiceResult('education-presential-2026', ['provider.municipality_ibge' => '3509502']);
    expect($wrong['reason_codes'])->toBe(['RTC_PERSON_PRESENCE_ESTABLISHMENT_CONFLICT']);
    $missing = annexServiceResult('education-presential-2026', ['rtc.performance_state' => null]);
    expect($missing['missing_facts'])->toBe(['rtc.performance_state']);
});

it('excludes only audited unpaid health glosa and composes it with supported adjustments', function (): void {
    $unpaid = annexServiceResult('health-unpaid-glosa-2026');
    expect($unpaid['decision']['base']['taxable'])->toBe('900.00')->and($unpaid['decision']['ibs']['nominal_amount'])->toBe('0.36')
        ->and($unpaid['decision']['cbs']['nominal_amount'])->toBe('3.24')
        ->and($unpaid['decision']['base']['adjustments'][0]['nonpayment_evidence_id'])->toBe('FIXTURE.MEDICAL.NONPAYMENT');
    $paid = annexServiceResult('health-paid-glosa-2026');
    expect($paid['decision']['base']['taxable'])->toBe('1000.00')->and($paid['decision']['cbs']['nominal_amount'])->toBe('3.60');
    $discount = ['kind' => 'UNCONDITIONAL_DISCOUNT', 'amount' => '950.00', 'evidence_id' => 'discount-proof',
        'document_id' => 'dps-1', 'depends_on_later_event' => false];
    $over = annexServiceResult('health-unpaid-glosa-2026', ['rtc.base_adjustments' => [$discount]]);
    expect($over['reason_codes'])->toBe(['RTC_HEALTH_GLOSA_EXCEEDS_REMAINING_BASE']);
});

it('requires the exact health nonpayment and scope proof', function (): void {
    $missing = annexServiceResult('health-unpaid-glosa-2026', ['rtc.health_glosa_nonpayment_evidence_id' => null]);
    expect($missing['status'])->toBe('ACTION_REQUIRED')->and($missing['missing_facts'])->toBe(['rtc.health_glosa_nonpayment_evidence_id']);
    $amount = annexServiceResult('health-presential-2026', ['rtc.health_medical_audit_glosa_amount' => null]);
    expect($amount['missing_facts'])->toBe(['rtc.health_medical_audit_glosa_amount']);
    $tooMuch = annexServiceResult('health-unpaid-glosa-2026', ['rtc.health_medical_audit_glosa_amount' => '1001.00']);
    expect($tooMuch['status'])->toBe('INVALID')->and($tooMuch['reason_codes'])->toBe(['RTC_HEALTH_GLOSA_AMOUNT_INVALID']);
    $mixed = annexServiceResult('education-mixed-consideration-2026');
    expect($mixed['status'])->toBe('ACTION_REQUIRED')->and($mixed['reason_codes'])->toBe(['RTC_MIXED_SERVICES_REQUIRE_SEPARATE_TREATMENT']);
});

it('refuses a mismatched annex NBS and requires special education audience evidence', function (): void {
    $wrong = annexServiceResult('education-nbs-conflict-2026');
    expect($wrong['status'])->toBe('INVALID')->and($wrong['reason_codes'])->toBe(['RTC_ANNEX_SERVICE_NBS_CONFLICT']);
    $special = annexServiceResult('education-special-2026');
    expect($special['status'])->toBe('VALID')->and($special['decision']['treatment']['annex_item'])->toBe('9');
    $missing = annexServiceResult('education-special-2026', ['rtc.special_education_evidence_id' => null]);
    expect($missing['missing_facts'])->toBe(['rtc.special_education_evidence_id']);
});

it('retains every statutory annex row and refuses unavailable temporal versions', function (): void {
    [$knowledge, $scenario] = annexServiceCase();
    $spec = $knowledge['documents']['rtc_code_catalog']['annex_service_reduction'];
    expect($spec['annexes']['EDUCATION']['items'])->toHaveCount(9)->and($spec['annexes']['HEALTH']['items'])->toHaveCount(30)
        ->and($spec['annexes']['HEALTH']['items'][29]['nbs'])->toBe('1.2301.99.0');
    foreach (['revoked_at', 'superseded_at'] as $key) {
        $stale = [...$spec, $key => $scenario->input->context->knowledgeAt->format(DATE_ATOM)];
        $result = (new AnnexServiceReductionQualifier)->resolve($scenario->input->context->facts,
            $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt, $stale);
        expect($result['reason'])->toBe('RTC_ANNEX_REDUCTION_RULE_NOT_AVAILABLE');
    }
});

it('does not invent future rates for the reduced profile', function (int $year): void {
    $result = annexServiceResult('education-rate-gap-'.$year);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and(implode('|', $result['reason_codes']))->toContain('RTC_RATE_NOT_PROVEN');
})->with([2027, 2028, 2029, 2030, 2031, 2032, 2033]);

it('keeps proved input credits independent from the reduced output debit and denies personal use', function (): void {
    $input = annexServiceResult('health-presential-2026', [
        'rtc.credit_context' => 'INPUT', 'rtc.acquirer_regime' => 'REGULAR',
        'rtc.input_document_id' => 'independent-acquisition-document', 'rtc.input_document_valid' => true,
        'rtc.prior_tax_extinguished' => true, 'rtc.input_ibs_amount' => '2.00', 'rtc.input_cbs_amount' => '18.00',
        'rtc.credit_adjustments' => [],
    ]);
    expect($input['decision']['ibs']['nominal_amount'])->toBe('0.40')
        ->and($input['decision']['ibs']['credit']['amount'])->toBe('2.00')
        ->and($input['decision']['cbs']['credit']['amount'])->toBe('18.00');
    $personal = annexServiceResult('health-presential-2026', ['rtc.credit_context' => 'PERSONAL_USE']);
    expect($personal['decision']['ibs']['credit']['status'])->toBe('DENIED')
        ->and($personal['decision']['cbs']['credit']['amount'])->toBe('0.00');
});
