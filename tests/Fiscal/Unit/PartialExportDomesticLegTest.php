<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function partialExportResult(array $overrides = []): array
{
    $factory = new FiscalEngineFactory;
    $resourceRoot = dirname(__DIR__, 3).'/resources/fiscal';
    $knowledge = $factory->captureKnowledge($resourceRoot, 'bundles/consumption-phase4b-2026.09.28-6.json');
    $source = json_decode((string) file_get_contents($resourceRoot.'/scenarios/consumption-phase4b-2026.09.28-1/export-partial-documented-2026.json'),
        true, 512, JSON_THROW_ON_ERROR);
    $source['scenario_id'] = 'partial-export-domestic-leg';
    $source['knowledge_at'] = '2026-09-28T12:04:00-03:00';
    $source['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $source['catalog_versions'] = $knowledge['catalog_versions'];
    $source['facts']['rtc.destination_basis'] = 'RECIPIENT_DOMICILE';
    $source['facts'] = array_merge($source['facts'], $overrides);
    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($source['facts'][$key]);
        }
    }
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $documents = $knowledge['documents'];
    $engine = new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog']));

    return $engine->evaluate($scenario->input->context->facts,
        $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
}

it('requires the Brazilian recipient of a partial export before valuing the domestic leg', function (): void {
    $result = partialExportResult();

    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['reason_codes'])->toContain('RTC_PARTIAL_EXPORT_DOMESTIC_ROLE_UNPROVEN')
        ->and($result['missing_facts'])->toContain('rtc.export_domestic_recipient_id', 'rtc.export_domestic_recipient_country');
});

it('calculates 2026 nominal IBS and CBS only on the evidenced Brazilian share', function (): void {
    $result = partialExportResult([
        'rtc.export_domestic_recipient_id' => '33333333000191',
        'rtc.export_domestic_recipient_country' => 'BR',
        'rtc.export_domestic_document_id' => 'dps-domestic-1',
    ]);
    $nominal = array_values(array_filter($result['trace'], static fn (array $row): bool => $row['stage'] === 'PARTIAL_EXPORT_DOMESTIC_NOMINAL_CALCULATION'))[0];

    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($result['reason_codes'])->toContain('RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN')
        ->and($nominal['foreign_immune_gross_brl'])->toBe('400.00')
        ->and($nominal['domestic_taxable_base_brl'])->toBe('600.00')
        ->and($nominal['ibs_state_nominal_brl'])->toBe('0.60')
        ->and($nominal['ibs_municipal_nominal_brl'])->toBe('0.00')
        ->and($nominal['cbs_nominal_brl'])->toBe('5.40')
        ->and($nominal['cindop'])->toBe('100302');
});

it('rejects a claimed domestic recipient abroad and does not allocate unproved adjustments', function (): void {
    $facts = ['rtc.export_domestic_recipient_id' => '33333333000191',
        'rtc.export_domestic_recipient_country' => 'US'];
    $conflict = partialExportResult($facts);
    expect($conflict['status'])->toBe('INVALID')
        ->and($conflict['reason_codes'])->toContain('RTC_PARTIAL_EXPORT_DOMESTIC_RECIPIENT_CONFLICT');

    $unallocated = partialExportResult(array_merge($facts, [
        'rtc.export_domestic_recipient_country' => 'BR',
        'rtc.base_adjustments' => [['kind' => 'INCLUDED_CHARGE', 'amount' => '10.00', 'evidence_id' => 'charge-1']],
    ]));
    expect($unallocated['status'])->toBe('ACTION_REQUIRED')
        ->and($unallocated['reason_codes'])->toContain('RTC_PARTIAL_EXPORT_ADJUSTMENT_ALLOCATION_UNPROVEN');
});
