<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\HealthPlanIntermediationB38;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

require_once dirname(__DIR__, 3).'/scripts/agent_b/travel_agency_b36_fixture.php';

// B38 (4B.4.10): intermediação de planos de assistência à saúde (LC 214, art. 240 c/c art. 237).

const B38_BUNDLE = 'bundles/consumption-phase4b-2026.11.06-b38-draft1.json';

/** @return array<string, mixed> */
function b38Evaluate(array $overrides = [], ?array $knowledge = null): array
{
    $knowledge ??= travelB36Knowledge(B38_BUNDLE);
    $scenario = travelB36Scenario(array_replace(['service.canonical_id' => 'CTRIBNAC_100104', 'rtc.specific_regime' => 'HEALTH_PLAN_INTERMEDIATION',
        'rtc.treatment_rule_id' => 'RTC.TREATMENT.LC214.ART240.B38-1', 'rtc.treatment_evidence_id' => 'FIXTURE.BROKER.REGIME',
        'rtc.health_plan_intermediation_scope_evidence_id' => 'FIXTURE.BROKERAGE.CONTRACT', 'rtc.health_plan_operator_id' => 'FIXTURE.OPERATOR.ANS',
        'rtc.service_nbs_code' => '1.0910.20.00', 'rtc.service_nbs_evidence_id' => 'FIXTURE.NBS.BROKERAGE',
        'rtc.travel_agency_intermediation' => null, 'rtc.travel_agency_scope_evidence_id' => null, 'rtc.travel_agency_settlement' => null], $overrides));
    $scenario['knowledge_at'] = $knowledge['documents']['bundle_manifest']['knowledge_available_at'];

    return evaluate156($scenario, $knowledge);
}

it('quotes arts. 237 and 240 literally from the hashed Planalto snapshot', function (): void {
    $path = resource_path('fiscal/sources/official/lc214-planalto-observed-20261103.htm');
    expect(hash_file('sha256', $path))->toBe(HealthPlanIntermediationB38::POLICY['source_hash']);
    $raw = (string) file_get_contents($path);
    if (! mb_check_encoding($raw, 'UTF-8')) {
        $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    }
    $text = (string) preg_replace('/\s+/u', ' ', html_entity_decode((string) preg_replace('/<[^>]+>/', ' ', $raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    foreach (['Os serviços de intermediação de planos de assistência à saúde ficam sujeitos à incidência do IBS e da CBS sobre o valor da operação pela mesma alíquota aplicável ao plano de assistência à saúde.',
        'são nacionalmente uniformes e correspondem às alíquotas de referência de cada esfera federativa, reduzidas em 60% (sessenta por cento).'] as $excerpt) {
        expect(mb_substr_count($text, $excerpt))->toBe(1);
    }
});

it('taxes the intermediation on the operation value at the 2026 national rates reduced by 60%', function (): void {
    $result = b38Evaluate();
    expect($result['status'])->toBe('VALID');
    $decision = $result['decision'];
    expect($decision['base']['taxable'])->toBe('1000.00')
        ->and($decision['treatment']['regime'])->toBe('HEALTH_PLAN_INTERMEDIATION_SPECIFIC')
        ->and($decision['treatment']['rate_basis'])->toBe('REFERENCE_RATES_UNIFORM_NATIONAL_ART237')
        ->and($decision['classification']['cclasstrib'])->toBe('011003')
        ->and($decision['document']['issuer_fields']['IBSCBS.valores.trib.CST'])->toBe('011')
        ->and($decision['cbs']['federal_rate_percent'])->toBe('0.36')
        ->and($decision['cbs']['nominal_amount'])->toBe('3.60')
        ->and($decision['ibs']['nominal_amount'])->toBe('0.40');
});

it('refuses 2027+ at the qualifier, Simples intermediaries and missing proofs', function (): void {
    $knowledge = travelB36Knowledge(B38_BUNDLE);
    $codes = $knowledge['documents']['rtc_code_catalog'];
    $knownAt = new DateTimeImmutable('2026-12-01T00:00:00Z');
    $facts = function (array $overrides) use ($knowledge) {
        $scenario = travelB36Scenario(array_replace(['service.canonical_id' => 'CTRIBNAC_100104', 'rtc.specific_regime' => 'HEALTH_PLAN_INTERMEDIATION',
            'rtc.treatment_rule_id' => 'RTC.TREATMENT.LC214.ART240.B38-1', 'rtc.health_plan_intermediation_scope_evidence_id' => 'S',
            'rtc.health_plan_operator_id' => 'O', 'rtc.travel_agency_intermediation' => null, 'rtc.travel_agency_scope_evidence_id' => null,
            'rtc.travel_agency_settlement' => null], $overrides));

        return (new FiscalEngineFactory)->scenarioLoaderFromKnowledge($knowledge)
            ->fromArray($scenario)->input->context->facts;
    };
    $q = new HealthPlanIntermediationB38;
    $on = LocalDate::fromString('2026-10-01');
    expect($q->resolve($facts([]), LocalDate::fromString('2027-01-01'), $knownAt, $codes)['reason'])
        ->toBe('RTC_UNIFORM_NATIONAL_REFERENCE_RATE_ROUTE_NOT_MATERIALIZED')
        ->and($q->resolve($facts(['rtc.supplier_regime' => 'SIMPLES_NACIONAL']), $on, $knownAt, $codes)['reason'])
        ->toBe('RTC_HEALTH_PLAN_INTERMEDIATION_SIMPLES_STAYS_IN_SIMPLES')
        ->and($q->resolve($facts([]), $on, $knownAt, $codes)['status'])->toBe('RESOLVED');
    expect(b38Evaluate(['rtc.health_plan_operator_id' => null])['reason_codes'])->toBe(['RTC_HEALTH_PLAN_INTERMEDIATION_PROOF_MISSING']);
});

it('keeps other insurance brokerage NBS outside the rule', function (): void {
    expect(b38Evaluate(['rtc.service_nbs_code' => '1.0906.11.00'])['reason_codes'])->toBe(['RTC_ENGINE_DECISION_RULE_NOT_PROVEN']);
});
