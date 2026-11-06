<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\TravelAgencyRegimeB36;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

require_once dirname(__DIR__).'/agent_a/handoff156_fixture.php';
require_once dirname(__DIR__).'/agent_a/domicile151_fixture.php';

const B36_BUNDLE = 'bundles/consumption-phase4b-2026.11.06-b36-draft1.json';

/** A151 domicile report for the regular SaaS parties (B27 gate). @return array<string, mixed> */
function travelB36DomicileReport(string $operationDate = '2026-09-25', string $knownAt = '2026-11-05T12:00:00Z'): array
{
    $input = array_replace(domicile151Fixture(), ['operation_date' => $operationDate, 'supplier_id' => '11111111000191',
        'acquirer_id' => '22222222000191', 'recipient_id' => '22222222000191', 'knowledge_at' => $knownAt]);
    foreach (['scope_evidence', 'registry_evidence', 'supply_establishment_evidence'] as $key) {
        $record = $input[$key]['record'];
        $record['entity_id'] = '22222222000191';
        if ($key === 'registry_evidence') {
            foreach ($record['establishments'] as $i => $establishment) {
                $record['establishments'][$i]['entity_id'] = '22222222000191';
            }
            $record['establishments'][1]['uf'] = 'SP';
            $record['establishments'][1]['municipality_ibge'] = '3509502';
        }
        $record['observed_at'] = min($knownAt, max($record['observed_at'], $operationDate.'T23:00:00Z'));
        $input[$key] = domicile151Envelope($record);
    }

    return domicile151Qualifier()->qualify(domicile151Rebind($input));
}

/**
 * Synthetic agency settlement: 1000.00 charged, 50.00 third-party commission, 700.00 passed to the intermediated hotel.
 * Labels are fixtures, not real documents.
 *
 * @return array<string, mixed>
 */
function travelB36Settlement(): array
{
    return ['contract' => TravelAgencyRegimeB36::CONTRACT, 'currency' => 'BRL', 'document_id' => 'dps-1',
        'agency_operation_document_id' => 'FIXTURE.AGENCY.VOUCHER', 'total_charged_to_user' => '1000.00',
        'third_party_commissions' => [['id' => 'C1', 'payer_id' => 'FIXTURE.AIRLINE', 'amount' => '50.00', 'evidence_id' => 'FIXTURE.COMMISSION']],
        'supplier_pass_through' => [['id' => 'P1', 'supplier_id' => 'FIXTURE.HOTEL', 'supporting_document_id' => 'FIXTURE.HOTEL.INVOICE',
            'amount' => '700.00', 'evidence_id' => 'FIXTURE.TRANSFER']]];
}

/**
 * @param  array<string, mixed>  $overrides  null removes a fact
 * @return array<string, mixed>
 */
function travelB36Scenario(array $overrides = []): array
{
    $root = dirname(__DIR__, 2).'/resources/fiscal/';
    $source = json_decode((string) file_get_contents($root.'scenarios/consumption-phase4b/regular-saas-2026.json'), true, 512, JSON_THROW_ON_ERROR);
    $manifest = json_decode((string) file_get_contents($root.B36_BUNDLE), true, 512, JSON_THROW_ON_ERROR);
    $source['scenario_id'] = 'FIXTURE.TRAVEL_AGENCY.B36';
    $source['knowledge_at'] = $manifest['knowledge_available_at'];
    $source['facts'] = array_replace($source['facts'], [
        'service.canonical_id' => 'CTRIBNAC_090201', 'rtc.operation_kind' => 'SERVICE', 'rtc.delivery_mode' => 'REMOTE',
        'rtc.tax_treatment' => 'SPECIFIC_REGIME', 'rtc.specific_regime' => 'TRAVEL_AGENCY',
        'rtc.treatment_rule_id' => 'RTC.TREATMENT.LC214.ART288-291.B36-1', 'rtc.treatment_evidence_id' => 'FIXTURE.AGENCY.REGIME',
        'rtc.travel_agency_intermediation' => true, 'rtc.travel_agency_scope_evidence_id' => 'FIXTURE.AGENCY.SCOPE',
        'rtc.service_nbs_code' => '1.1805.24.00', 'rtc.service_nbs_evidence_id' => 'FIXTURE.NBS.PACKAGE',
        'rtc.travel_agency_settlement' => [travelB36Settlement()],
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
function travelB36Knowledge(string $bundle = B36_BUNDLE): array
{
    static $cache = [];

    return $cache[$bundle] ??= (new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'), $bundle);
}

/** @return array<string, mixed> */
function travelB36Evaluate(array $overrides = [], ?array $knowledge = null): array
{
    return evaluate156(travelB36Scenario($overrides), $knowledge ?? travelB36Knowledge());
}

/** @return array<string, mixed> */
function travelB36WithSettlement(array $changes): array
{
    return travelB36Evaluate(['rtc.travel_agency_settlement' => [array_replace(travelB36Settlement(), $changes)]]);
}
