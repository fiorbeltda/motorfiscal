<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Engine\DecisionStatus;
use App\Modules\Fiscal\Core\Engine\FiscalEvaluationResult;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** @param array<string, mixed> $overrides
 */
function postIssResult(array $overrides = []): FiscalEvaluationResult
{
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.09.28-7.json');
    $source = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/consumption-phase4b-2026.09.28-1/regular-saas-2033-rate-gap.json')),
        true, 512, JSON_THROW_ON_ERROR);
    $source['scenario_id'] = 'integrated-post-iss-2033';
    $source['knowledge_at'] = '2033-02-02T10:00:00-03:00';
    $source['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $source['catalog_versions'] = $knowledge['catalog_versions'];
    $source['facts']['rtc.legacy_event_spans_transition'] = false;
    $source['facts'] = array_merge($source['facts'], $overrides);
    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($source['facts'][$key]);
        }
    }
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);

    return $factory->makeFromKnowledge($knowledge)->evaluate($scenario->input);
}

it('routes an ordinary 2033 operation to IBS/CBS and reports the missing legal rate', function (): void {
    $result = postIssResult();
    $started = array_values(array_map(static fn (array $event): string => $event['payload']['node_id'],
        array_filter($result->trace, static fn (array $event): bool => $event['type'] === 'DECISION_NODE_STARTED')));

    expect($result->status)->toBe(DecisionStatus::ACTION_REQUIRED)
        ->and($result->reasonCodes)->toContain('RTC_RATE_NOT_PROVEN:IBS_STATE')
        ->and($started)->toBe(['facts', 'classification', 'consumption_reform'])
        ->and($result->decision)->toBe([]);
});

it('asks about cross-year legacy timing and does not suppress a possible old-tax event', function (): void {
    $unproven = postIssResult(['rtc.legacy_event_spans_transition' => null]);
    expect($unproven->status)->toBe(DecisionStatus::ACTION_REQUIRED)
        ->and($unproven->reasonCodes)->toContain('RTC_LEGACY_EVENT_TIMING_UNPROVEN')
        ->and($unproven->missingFacts)->toContain('rtc.legacy_event_spans_transition');

    $spanning = postIssResult(['rtc.legacy_event_spans_transition' => true]);
    expect($spanning->status)->toBe(DecisionStatus::ACTION_REQUIRED)
        ->and($spanning->reasonCodes)->toContain('RTC_CROSS_YEAR_LEGACY_TAX_RULE_NOT_MATERIALIZED');
});

it('requires IBS/CBS evaluation after ISS extinction', function (): void {
    $result = postIssResult(['rtc.evaluate' => false]);

    expect($result->status)->toBe(DecisionStatus::ACTION_REQUIRED)
        ->and($result->reasonCodes)->toContain('RTC_EVALUATION_REQUIRED_AFTER_ISS_EXTINCTION');
});

/** @return array<string, mixed> */
function postIssSyntheticKnowledge(string $bundlePath): array
{
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), $bundlePath);
    $testHash = str_repeat('a', 64);
    foreach ([['IBS_STATE', 'SP', '1.00'], ['IBS_MUNICIPAL', '3509502', '2.00'], ['CBS', 'BR', '3.00']] as [$tax, $jurisdiction, $percent]) {
        $knowledge['documents']['rtc_rate_catalog']['rates'][] = [
            'tax' => $tax, 'jurisdiction' => $jurisdiction, 'percent' => $percent,
            'valid_from' => '2033-01-01', 'valid_to' => '2033-12-31',
            'published_at' => '2032-12-01T00:00:00-03:00', 'status' => 'ACTIVE',
            'source_id' => 'TEST.SYNTHETIC.RATE', 'source_hash' => $testHash,
        ];
    }
    $knowledge['documents']['rtc_code_catalog']['document_layouts'][] = [
        'version' => 'TEST.SYNTHETIC.2033', 'valid_from' => '2033-01-01',
        'valid_to' => '2033-12-31', 'sandbox_coverage_through' => '2033-12-31',
        'published_at' => '2032-12-01T00:00:00-03:00', 'status' => 'ACTIVE',
        'source_hash' => $testHash,
    ];
    $knowledge['documents']['rtc_code_catalog']['cclasstrib']['000001']['valid_to'] = '2033-12-31';
    foreach (['rtc_rate_catalog', 'rtc_code_catalog'] as $key) {
        $hash = CanonicalJson::hash($knowledge['documents'][$key]);
        $knowledge['content_hashes'][$key] = $hash;
        $knowledge['documents']['bundle_manifest']['content_hashes'][$key] = $hash;
    }
    $knowledge['content_hashes']['bundle_manifest'] = CanonicalJson::hash($knowledge['documents']['bundle_manifest']);

    return $knowledge;
}

it('can assemble a post-ISS projection when future rates and layout are supplied in test knowledge', function (): void {
    $factory = new FiscalEngineFactory;
    $knowledge = postIssSyntheticKnowledge('bundles/consumption-phase4b-2026.09.28-7.json');
    $source = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/consumption-phase4b-2026.09.28-1/regular-saas-2033-rate-gap.json')),
        true, 512, JSON_THROW_ON_ERROR);
    $source['scenario_id'] = 'post-iss-2033-synthetic-test';
    $source['knowledge_at'] = '2033-02-02T10:00:00-03:00';
    $source['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $source['catalog_versions'] = $knowledge['catalog_versions'];
    $source['facts']['rtc.legacy_event_spans_transition'] = false;
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $result = $factory->makeFromKnowledge($knowledge)->evaluate($scenario->input);

    expect($result->status)->toBe(DecisionStatus::VALID_WITH_WARNINGS)
        ->and($result->decision['iss']['status'])->toBe('NOT_APPLICABLE')
        ->and($result->decision['consumption_reform']['decision']['ibs']['state_amount'])->toBe('10.00')
        ->and($result->decision['consumption_reform']['decision']['ibs']['municipal_amount'])->toBe('20.00')
        ->and($result->decision['consumption_reform']['decision']['cbs']['nominal_amount'])->toBe('30.00')
        ->and($result->decision['dps_projection']['iss']['status'])->toBe('NOT_APPLICABLE')
        ->and($result->decision['dps_projection']['rtc']['schema_version'])->toBe('TEST.SYNTHETIC.2033');
});

/** @param array<string, mixed> $federalFacts */
function postIssFederalResult(array $federalFacts): FiscalEvaluationResult
{
    $factory = new FiscalEngineFactory;
    $knowledge = postIssSyntheticKnowledge('bundles/consumption-phase4b-2026.09.28-9.json');
    $source = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/consumption-phase4b-2026.09.28-1/regular-saas-2033-rate-gap.json')),
        true, 512, JSON_THROW_ON_ERROR);
    $source['scenario_id'] = 'post-iss-2033-federal-evaluation';
    $source['knowledge_at'] = '2033-02-02T10:00:00-03:00';
    $source['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $source['catalog_versions'] = $knowledge['catalog_versions'];
    $source['facts']['rtc.legacy_event_spans_transition'] = false;
    $source['facts']['federal.evaluate_withholdings'] = true;
    $source['facts'] = array_merge($source['facts'], $federalFacts);
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);

    return $factory->makeFromKnowledge($knowledge)->evaluate($scenario->input);
}

it('routes documented 2033 federal withholding facts into phase 4A without asserting a legacy PIS/Cofins amount', function (): void {
    $result = postIssFederalResult([
        'payment.occurred' => true, 'payment.date' => '2033-02-02', 'payment.gross_amount' => '1000.00',
    ]);

    expect($result->status)->toBe(DecisionStatus::ACTION_REQUIRED)
        ->and($result->reasonCodes)->not->toContain('FEDERAL_POST_2032_WITHHOLDING_PROFILE_NOT_MATERIALIZED')
        ->and(array_column($result->trace, 'type'))->toContain('FEDERAL_WITHHOLDINGS');
});

it('requires a post-2032 payment date before a positive federal withholding assessment', function (): void {
    $missing = postIssFederalResult(['payment.occurred' => true]);
    expect($missing->status)->toBe(DecisionStatus::ACTION_REQUIRED)
        ->and($missing->reasonCodes)->toContain('FEDERAL_POST_2032_PAYMENT_DATE_UNPROVEN')
        ->and($missing->missingFacts)->toContain('payment.date');

    $old = postIssFederalResult(['payment.occurred' => true, 'payment.date' => '2026-12-31']);
    expect($old->status)->toBe(DecisionStatus::ACTION_REQUIRED)
        ->and($old->reasonCodes)->toContain('FEDERAL_POST_2032_PAYMENT_DATE_UNPROVEN');

    $oldCredit = postIssFederalResult([
        'payment.occurred' => true, 'payment.date' => '2033-02-02',
        'payment.accounting_credit_date' => '2026-12-31',
    ]);
    expect($oldCredit->status)->toBe(DecisionStatus::ACTION_REQUIRED)
        ->and($oldCredit->reasonCodes)->toContain('FEDERAL_POST_2032_CREDIT_DATE_REQUIRES_LEGACY_REVIEW');
});

it('preserves the specific public 4A revalidation reason in 2033', function (): void {
    $result = postIssFederalResult([
        'payment.occurred' => true, 'payment.date' => '2033-02-02', 'payment.gross_amount' => '1000.00',
        'payer.person_type' => 'FEDERAL_PUBLIC_ADMINISTRATION', 'payer.public_entity_type' => 'DIRECT',
        'payer.tax_regime' => 'PUBLIC_ENTITY', 'payer.head_office_tax_id' => '12345678000199',
        'public.annex_category' => 'GENERAL_SERVICES', 'public.glosa_without_new_invoice' => false,
        'public.has_late_additions' => false, 'public.beneficiary_treatment' => 'STANDARD',
        'federal.pis_status' => 'APPLICABLE', 'federal.cofins_status' => 'APPLICABLE',
        'federal.csll_status' => 'APPLICABLE', 'federal.income_nature_code' => '17001',
        'inss.service_group' => 'OUTSIDE_EXHAUSTIVE_LIST',
        'inss.service_category' => 'SOFTWARE_DEVELOPMENT', 'inss.execution_mode' => 'CONTRACT_FOR_RESULT',
        'invoice.issue_date' => '2033-02-02', 'invoice.gross_amount' => '1000.00',
    ]);

    expect($result->status)->toBe(DecisionStatus::ACTION_REQUIRED)
        ->and($result->reasonCodes)->toContain('PUBLIC_LEGACY_PIS_COFINS_REVALIDATION_REQUIRED');
});

it('uses the private 4A temporal profile without retaining PIS or Cofins in 2033', function (): void {
    $result = postIssFederalResult([
        'payment.occurred' => true, 'payment.date' => '2033-02-02', 'payment.gross_amount' => '1000.00',
        'payer.person_type' => 'PRIVATE_LEGAL_ENTITY', 'payer.tax_regime' => 'LUCRO_REAL',
        'federal.service_category' => 'PROFESSIONAL_SERVICE', 'federal.csll_status' => 'APPLICABLE',
        'inss.service_group' => 'OUTSIDE_EXHAUSTIVE_LIST',
        'inss.service_category' => 'SOFTWARE_DEVELOPMENT', 'inss.execution_mode' => 'CONTRACT_FOR_RESULT',
    ]);
    $events = array_values(array_filter($result->trace,
        static fn (array $event): bool => $event['type'] === 'FEDERAL_WITHHOLDINGS'));
    $decisions = $events[0]['payload']['pcc']['decisions'] ?? [];
    $statuses = [];
    foreach ($decisions as $decision) {
        $statuses[$decision['tax']] = $decision['status'];
    }

    expect($events)->not->toBeEmpty()
        ->and($statuses['PIS'] ?? null)->toBe('NOT_APPLICABLE')
        ->and($statuses['COFINS'] ?? null)->toBe('NOT_APPLICABLE');
});
