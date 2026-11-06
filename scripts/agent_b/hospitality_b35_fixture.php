<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

require_once dirname(__DIR__).'/agent_a/handoff156_fixture.php';

const B35_BUNDLE = 'bundles/consumption-phase4b-2026.11.06-b35-draft1.json';

/**
 * B35 hotel scenario, derived from the presential education scenario (same parties, same municipality).
 * Evidence identifiers are fixture labels, not real documents.
 *
 * @param  array<string, mixed>  $overrides  null removes a fact
 * @return array<string, mixed>
 */
function hospitalityB35Scenario(array $overrides = []): array
{
    $root = dirname(__DIR__, 2).'/resources/fiscal/';
    $source = json_decode((string) file_get_contents($root.'scenarios/consumption-integrated-2026.10.01-6/integrated-education-presential-2026.json'),
        true, 512, JSON_THROW_ON_ERROR);
    $manifest = json_decode((string) file_get_contents($root.B35_BUNDLE), true, 512, JSON_THROW_ON_ERROR);
    $source['scenario_id'] = 'FIXTURE.HOSPITALITY.B35';
    $source['knowledge_at'] = $manifest['knowledge_available_at'];
    foreach (['rtc.annex_service_description_confirmed', 'rtc.annex_service_only_consideration', 'rtc.annex_service_scope_evidence_id',
        'rtc.annex_service_category', 'rtc.annex_service_item'] as $key) {
        unset($source['facts'][$key]);
    }
    $source['facts'] = array_replace($source['facts'], [
        'service.canonical_id' => 'CTRIBNAC_090101', 'service.ctribnac_code' => '090101',
        'rtc.tax_treatment' => 'SPECIFIC_REGIME', 'rtc.specific_regime' => 'HOSPITALITY',
        'rtc.treatment_rule_id' => 'RTC.TREATMENT.LC214.ART277-283.B35-1', 'rtc.treatment_evidence_id' => 'FIXTURE.HOTEL.REGIME',
        'rtc.hospitality_activity' => 'HOTEL', 'rtc.hospitality_scope_evidence_id' => 'FIXTURE.HOTEL.SCOPE',
        'rtc.hospitality_food_beverage_in_price' => false, 'rtc.hotel_unit_kind' => 'EXCLUSIVE_GUEST_UNITS_ESTABLISHMENT',
        'rtc.hotel_temporary_lodging' => true, 'rtc.service_nbs_code' => '1.0303.11.00',
        'rtc.service_nbs_evidence_id' => 'FIXTURE.NBS.LODGING', 'rtc.service_presence_evidence_id' => 'FIXTURE.GUEST.STAY',
    ], $overrides);
    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($source['facts'][$key]);
        }
    }

    return $source;
}

/** @return array<string, mixed> */
function hospitalityB35Knowledge(string $bundle = B35_BUNDLE): array
{
    static $cache = [];

    return $cache[$bundle] ??= (new FiscalEngineFactory)->captureKnowledge(resource_path('fiscal'), $bundle);
}

/** @return array<string, mixed> */
function hospitalityB35Evaluate(array $overrides = [], ?array $knowledge = null, bool $integrated = false): array
{
    return evaluate156(hospitalityB35Scenario($overrides), $knowledge ?? hospitalityB35Knowledge(), $integrated);
}
