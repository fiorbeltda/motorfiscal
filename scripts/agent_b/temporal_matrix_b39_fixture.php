<?php

declare(strict_types=1);

require_once __DIR__.'/hospitality_b35_fixture.php';
require_once __DIR__.'/real_estate_b37_fixture.php';

const B39_BUNDLE = 'bundles/consumption-phase4b-2026.11.06-b38-draft1.json';

const B39_YEARS = ['2026', '2027', '2028', '2029', '2030', '2031', '2032', '2033'];

/**
 * B39: family scenarios evaluated on the latest bundle. Only the operation/payment dates move; every other fact is kept,
 * so each cell shows what the engine does with the published parameters for that year, never an estimate.
 *
 * @return array<string, array<string, mixed>>
 */
function temporalB39Families(): array
{
    $broker = ['service.canonical_id' => 'CTRIBNAC_100104', 'rtc.specific_regime' => 'HEALTH_PLAN_INTERMEDIATION',
        'rtc.treatment_rule_id' => 'RTC.TREATMENT.LC214.ART240.B38-1', 'rtc.treatment_evidence_id' => 'FIXTURE.BROKER.REGIME',
        'rtc.health_plan_intermediation_scope_evidence_id' => 'FIXTURE.BROKERAGE.CONTRACT', 'rtc.health_plan_operator_id' => 'FIXTURE.OPERATOR.ANS',
        'rtc.service_nbs_code' => '1.0910.20.00', 'rtc.service_nbs_evidence_id' => 'FIXTURE.NBS.BROKERAGE',
        'rtc.travel_agency_intermediation' => null, 'rtc.travel_agency_scope_evidence_id' => null, 'rtc.travel_agency_settlement' => null];
    $saas = ['rtc.tax_treatment' => 'STANDARD', 'rtc.specific_regime' => null, 'rtc.treatment_rule_id' => null, 'rtc.treatment_evidence_id' => null,
        'rtc.travel_agency_intermediation' => null, 'rtc.travel_agency_scope_evidence_id' => null, 'rtc.travel_agency_settlement' => null,
        'rtc.service_nbs_code' => null, 'rtc.service_nbs_evidence_id' => null, 'service.canonical_id' => 'SOFTWARE_DEVELOPMENT',
        'rtc.operation_kind' => 'DIGITAL', 'rtc.delivery_mode' => 'DIGITAL'];

    return [
        'SAAS_STANDARD' => travelB36Scenario($saas),
        'HOTEL_B35' => hospitalityB35Scenario(),
        'TRAVEL_AGENCY_B36' => travelB36Scenario(),
        'REAL_ESTATE_LEASE_B37' => realEstateB37Scenario(),
        'HEALTH_PLAN_BROKER_B38' => travelB36Scenario($broker),
    ];
}

/** @return array<string, mixed> */
function temporalB39Cell(array $scenario, string $year, bool $probeNextBlocker = false): array
{
    $knowledge = travelB36Knowledge(B39_BUNDLE);
    if ($probeNextBlocker) {
        // SYNTHETIC_TEST, in memory only: drop the 2026-only local cIndOp reference to find the next blocker. Never a legal effect.
        unset($knowledge['documents']['rtc_code_catalog']['cindop_reference126']);
        $knowledge = refresh156($knowledge);
    }
    $date = $year.substr((string) $scenario['facts']['rtc.operation_date'], 4);
    $scenario['facts']['rtc.operation_date'] = $date;
    $scenario['competence_date'] = $date;
    if (isset($scenario['facts']['rtc.real_estate_payment_date'])) {
        $scenario['facts']['rtc.real_estate_payment_date'] = $date;
    }
    // Each year is evaluated after its own operation (30 days later), with the knowledge published up to now.
    $available = new DateTimeImmutable($knowledge['documents']['bundle_manifest']['knowledge_available_at']);
    $after = (new DateTimeImmutable($date.'T12:00:00Z'))->modify('+30 days');
    $knownAt = ($after > $available ? $after : $available)->format('Y-m-d\TH:i:s\Z');
    if (isset($scenario['facts']['rtc.destination_domicile_report'])) {
        $scenario['facts']['rtc.destination_domicile_report'] = [travelB36DomicileReport($date, $knownAt)];
    }
    $scenario['knowledge_at'] = $knownAt;
    try {
        $result = evaluate156($scenario, $knowledge);
    } catch (Throwable $exception) {
        return ['year' => $year, 'status' => 'INPUT_REJECTED', 'reasons' => [$exception->getMessage()]];
    }
    $cell = ['year' => $year, 'status' => $result['status'], 'reasons' => array_values($result['reason_codes'] ?? [])];
    if ($result['status'] === 'VALID') {
        $cell += ['taxable_base' => $result['decision']['base']['taxable'], 'cbs' => $result['decision']['cbs']['nominal_amount'],
            'ibs' => $result['decision']['ibs']['nominal_amount'], 'cbs_rate' => $result['decision']['cbs']['federal_rate_percent']];
    }

    return $cell;
}

/** @return array<string, list<array<string, mixed>>> */
function temporalB39Matrix(bool $probeNextBlocker = false): array
{
    $matrix = [];
    foreach (temporalB39Families() as $family => $scenario) {
        foreach (B39_YEARS as $year) {
            $matrix[$family][] = temporalB39Cell($scenario, $year, $probeNextBlocker);
        }
    }

    return $matrix;
}
