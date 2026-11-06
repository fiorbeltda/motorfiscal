<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DestinationDomicileReportB27;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;

require_once dirname(__DIR__, 3).'/scripts/agent_a/collection148_fixture.php';
require_once dirname(__DIR__, 3).'/scripts/agent_a/domicile151_fixture.php';
require_once dirname(__DIR__, 3).'/scripts/agent_a/handoff156_fixture.php';

const B27_KNOWN_AT = '2026-11-05T12:00:00-03:00';

/** A151 report for the SaaS scenario parties, supplied establishment in the given locality. @return array<string, mixed> */
function b27Report(string $uf = 'SP', string $municipality = '3509502', bool $naturalPerson = false): array
{
    $input = domicile151Fixture();
    $input = array_replace($input, ['operation_date' => '2026-09-25', 'supplier_id' => '11111111000191',
        'acquirer_id' => '22222222000191', 'recipient_id' => '22222222000191', 'knowledge_at' => '2026-11-05T12:00:00Z']);
    foreach (['scope_evidence', 'registry_evidence', 'supply_establishment_evidence'] as $key) {
        $record = $input[$key]['record'];
        $record['entity_id'] = '22222222000191';
        if ($key === 'registry_evidence') {
            foreach ($record['establishments'] as $i => $establishment) {
                $record['establishments'][$i]['entity_id'] = '22222222000191';
            }
            $record['establishments'][1]['uf'] = $uf;
            $record['establishments'][1]['municipality_ibge'] = $municipality;
            if ($naturalPerson) {
                $record += ['natural_person_subject_to_registration105' => true, 'natural_person105_role' => 'TAXPAYER',
                    'natural_person105_activity_evidence_id' => 'B27.ACTIVITY', 'registration_scheme' => 'CPF'];
                $record['party_kind'] = 'NATURAL_PERSON';
            }
        }
        $input[$key] = domicile151Envelope($record);
    }

    return domicile151Qualifier()->qualify(domicile151Rebind($input));
}

/** SaaS scenario on bundle 09.28-3 with the B27 gate (and the geography reference it needs) only in memory. */
function b27Run(?array $report, bool $enabled = true, mixed $policy = null): array
{
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.09.28-3.json');
    $catalog141 = json_decode((string) file_get_contents(resource_path('fiscal/catalogs/rtc-codes-2026.10.27-141-draft1.json')), true, 512, JSON_THROW_ON_ERROR);
    $codes = &$knowledge['documents']['rtc_code_catalog'];
    if ($enabled) {
        $codes[DestinationDomicileReportB27::KEY] = $policy ?? DestinationDomicileReportB27::POLICY;
        $codes['observed_geographic_reference'] = $catalog141['observed_geographic_reference'];
        $codes['observed_geographic_reference_policy'] = $catalog141['observed_geographic_reference_policy'];
    }
    unset($codes);
    $knowledge['documents']['fact_definitions'][] = ['key' => 'rtc.destination_domicile_report', 'type' => 'ARRAY', 'material' => true];
    $knowledge = refresh156($knowledge);
    $source = collection148Scenario();
    $source['knowledge_at'] = B27_KNOWN_AT;
    $source['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $source['catalog_versions'] = $knowledge['catalog_versions'];
    if ($report !== null) {
        $source['facts']['rtc.destination_domicile_report'] = [$report];
    }
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);

    return (new RtcScenarioRunService($factory))->evaluate($knowledge, $loaded)->toArray();
}

it('produces a qualified A151 report for the scenario parties', function (): void {
    expect(b27Report()['status'])->toBe('LOCAL_ENTITY_DOMICILE_QUALIFIED');
});

it('binds the engine destination to the replayed domicile report', function (): void {
    $result = b27Run(b27Report());
    expect($result['status'])->toBe('VALID');
    $stage = array_column($result['trace'], null, 'stage')['DESTINATION_DOMICILE_REPORT_B27'];
    expect($stage['reason'])->toBe('RTC_DESTINATION_DOMICILE_REPORT_BOUND')->and($stage['selected_establishment_id'])->toBe('BRANCH151')
        ->and($stage['locality'])->toBe(['uf' => 'SP', 'municipality_ibge' => '3509502'])->and($stage['natural_person105'])->toBeFalse()
        ->and($stage['authenticated'])->toBeFalse();
});

it('binds the establishment of an art. 105 natural person (B26) the same way', function (): void {
    $stage = array_column(b27Run(b27Report('SP', '3509502', true))['trace'], null, 'stage')['DESTINATION_DOMICILE_REPORT_B27'];
    expect($stage['status'])->toBe('RESOLVED')->and($stage['natural_person105'])->toBeTrue();
});

it('asks for the report when the gate is active and none is supplied', function (): void {
    $result = b27Run(null);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['reason_codes'])->toContain('RTC_DESTINATION_DOMICILE_REPORT_REQUIRED')
        ->and($result['missing_facts'])->toContain('rtc.destination_domicile_report');
});

it('refuses a report whose locality differs from the declared destination', function (): void {
    $result = b27Run(b27Report('RJ', '3304557'));
    expect($result['status'])->toBe('INVALID')->and($result['reason_codes'])->toContain('RTC_DESTINATION_DOMICILE_REPORT_CONFLICT');
});

it('refuses a tampered report even with a recomputed report hash', function (): void {
    $report = b27Report();
    $report['locality'] = ['uf' => 'SP', 'municipality_ibge' => '3509502'];
    $report['selected_establishment_id'] = 'HQ151';
    unset($report['report_hash']);
    $report['report_hash'] = CanonicalJson::hash($report);
    $result = b27Run($report);
    expect($result['status'])->toBe('INVALID')->and($result['reason_codes'])->toContain('RTC_DESTINATION_DOMICILE_REPORT_REPLAY_FAILED');
});

it('refuses a non-qualified report and a malformed policy', function (): void {
    $report = b27Report();
    $report['status'] = 'ACTION_REQUIRED';
    expect(b27Run($report)['reason_codes'])->toContain('RTC_DESTINATION_DOMICILE_REPORT_NOT_QUALIFIED')
        ->and(b27Run(b27Report(), true, ['version' => 'B27'])['reason_codes'])->toBe(['RTC_DESTINATION_DOMICILE_REPORT_POLICY_INVALID']);
});

it('keeps the historical decision without the gate', function (): void {
    expect(b27Run(null, false)['status'])->toBe('VALID');
});
it('installs the B27 gate, fact and question in a new bundle forked from B25', function (): void {
    $factory = new FiscalEngineFactory;
    $new = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.11.05-b27-draft1.json')['documents'];
    $old = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.11.05-b25-draft1.json')['documents'];
    expect(DestinationDomicileReportB27::enabled($new['rtc_code_catalog']))->toBeTrue()
        ->and(array_values(array_diff(array_keys($new['rtc_code_catalog']), array_keys($old['rtc_code_catalog']))))->toBe([DestinationDomicileReportB27::KEY])
        ->and($new['fact_definitions'])->toContain(['key' => 'rtc.destination_domicile_report', 'type' => 'ARRAY', 'material' => true])
        ->and(array_column($new['questions'], 'answer_type', 'fact_key')['rtc.destination_domicile_report'] ?? null)->toBe('ARRAY')
        ->and($new['rtc_code_catalog'])->toHaveKey('observed_geographic_reference');
});
