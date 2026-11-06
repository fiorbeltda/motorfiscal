<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\RealEstateRegimeB37;

require_once __DIR__.'/travel_agency_b36_fixture.php';

const B37_BUNDLE = 'bundles/consumption-phase4b-2026.11.06-b37-draft1.json';

/** Synthetic lease settlement: 1000.00 charged, 100.00 property tax and 200.00 condominium excluded. @return array<string, mixed> */
function realEstateB37Settlement(): array
{
    return ['contract' => RealEstateRegimeB37::CONTRACT, 'currency' => 'BRL', 'document_id' => 'dps-1',
        'property_registry_id' => 'FIXTURE.CIB.1', 'amount_charged' => '1000.00',
        'property_taxes_and_fees' => [['id' => 'IPTU', 'amount' => '100.00', 'evidence_id' => 'FIXTURE.IPTU']],
        'condominium_expenses' => [['id' => 'COND', 'amount' => '200.00', 'evidence_id' => 'FIXTURE.CONDOMINIUM']]];
}

/**
 * @param  array<string, mixed>  $overrides  null removes a fact
 * @return array<string, mixed>
 */
function realEstateB37Scenario(array $overrides = []): array
{
    $root = dirname(__DIR__, 2).'/resources/fiscal/';
    $source = json_decode((string) file_get_contents($root.'scenarios/consumption-phase4b/regular-saas-2026.json'), true, 512, JSON_THROW_ON_ERROR);
    $manifest = json_decode((string) file_get_contents($root.B37_BUNDLE), true, 512, JSON_THROW_ON_ERROR);
    $source['scenario_id'] = 'FIXTURE.REAL_ESTATE.B37';
    $source['knowledge_at'] = $manifest['knowledge_available_at'];
    $source['facts'] = array_replace($source['facts'], [
        'service.canonical_id' => 'CTRIBNAC_990101', 'rtc.operation_kind' => 'REAL_ESTATE', 'rtc.delivery_mode' => 'PRESENTIAL',
        'rtc.destination_basis' => 'PROPERTY', 'rtc.property_state' => 'SP', 'rtc.property_municipality_ibge' => '3509502',
        'rtc.property_registry_id' => 'FIXTURE.CIB.1', 'rtc.tax_treatment' => 'SPECIFIC_REGIME', 'rtc.specific_regime' => 'REAL_ESTATE',
        'rtc.treatment_rule_id' => 'RTC.TREATMENT.LC214.ART251-265.B37-1', 'rtc.treatment_evidence_id' => 'FIXTURE.REAL_ESTATE.REGIME',
        'rtc.real_estate_activity' => 'LEASE', 'rtc.real_estate_lease_form' => 'LEASE',
        'rtc.real_estate_contributor_basis' => 'REGULAR_LEGAL_ENTITY', 'rtc.real_estate_contributor_evidence_id' => 'FIXTURE.CNPJ.REGULAR',
        'rtc.property_use' => 'NON_RESIDENTIAL', 'rtc.lease_term_class' => 'OVER_90_DAYS', 'rtc.real_estate_payment_date' => '2026-09-25',
        'rtc.service_nbs_code' => '1.1002.20.00', 'rtc.service_nbs_evidence_id' => 'FIXTURE.NBS.LEASE',
        'rtc.real_estate_settlement' => [realEstateB37Settlement()],
        'rtc.destination_domicile_report' => [travelB36DomicileReport()],
    ], $overrides);
    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($source['facts'][$key]);
        }
    }

    return $source;
}

/** @return array<string, mixed> */
function realEstateB37Evaluate(array $overrides = [], ?array $knowledge = null): array
{
    return evaluate156(realEstateB37Scenario($overrides), $knowledge ?? travelB36Knowledge(B37_BUNDLE));
}

/** @return array<string, mixed> */
function realEstateB37WithSettlement(array $changes, array $overrides = []): array
{
    return realEstateB37Evaluate(['rtc.real_estate_settlement' => [array_replace(realEstateB37Settlement(), $changes)], ...$overrides]);
}
