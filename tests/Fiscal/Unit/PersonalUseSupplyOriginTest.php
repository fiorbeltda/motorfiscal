<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function personalOriginResult(array $overrides, string $bundleVersion = '13'): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.09.28-'.$bundleVersion.'.json');
    $source = json_decode((string) file_get_contents($root.'/scenarios/consumption-phase4b-2026.09.28-8/employee-below-market-nominal-2026.json'),
        true, 512, JSON_THROW_ON_ERROR);
    $source['scenario_id'] = 'personal-use-origin';
    $source['knowledge_at'] = $bundleVersion === '13' ? '2026-09-28T17:01:00-03:00' : '2026-09-28T16:31:00-03:00';
    $source['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $source['catalog_versions'] = $knowledge['catalog_versions'];
    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($source['facts'][$key]);
        } else {
            $source['facts'][$key] = $value;
        }
    }
    $scenario = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $documents = $knowledge['documents'];
    $engine = new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog']));

    return $engine->evaluate($scenario->input->context->facts,
        $scenario->input->context->competenceDate, $scenario->input->context->knowledgeAt);
}

it('requires documented self-provided origin for art 5 I b personal use', function (): void {
    $missing = personalOriginResult([]);
    expect($missing['status'])->toBe('ACTION_REQUIRED')
        ->and($missing['reason_codes'])->toContain('RTC_PERSONAL_USE_SUPPLY_ORIGIN_UNPROVEN')
        ->and($missing['missing_facts'])->toContain('rtc.personal_use_supply_origin', 'rtc.personal_use_origin_evidence_id');

    $conflict = personalOriginResult([
        'rtc.personal_use_supply_origin' => 'ACQUIRED_FROM_THIRD_PARTY',
        'rtc.personal_use_origin_evidence_id' => 'supplier-invoice-1',
    ]);
    expect($conflict['status'])->toBe('INVALID')
        ->and($conflict['reason_codes'])->toContain('RTC_PERSONAL_USE_LEGAL_BASIS_ORIGIN_CONFLICT');

    $valid = personalOriginResult([
        'rtc.personal_use_supply_origin' => 'SELF_PRODUCED_OR_PROVIDED',
        'rtc.personal_use_origin_evidence_id' => 'service-log-1',
    ]);
    $origin = array_values(array_filter($valid['trace'],
        static fn (array $row): bool => ($row['stage'] ?? null) === 'PERSONAL_USE_SUPPLY_ORIGIN'));
    expect($valid['status'])->toBe('ACTION_REQUIRED')
        ->and($valid['reason_codes'])->toContain('RTC_NON_ONEROUS_DOCUMENT_NOT_MATERIALIZED')
        ->and($origin[0]['evidence_id'])->toBe('service-log-1');
});

it('preserves the preceding candidate decision without the new fact', function (): void {
    $previous = personalOriginResult([], '12');
    expect($previous['status'])->toBe('ACTION_REQUIRED')
        ->and($previous['reason_codes'])->toContain('RTC_NON_ONEROUS_DOCUMENT_NOT_MATERIALIZED');
});
