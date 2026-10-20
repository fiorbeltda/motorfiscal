<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\ConsumptionReformEngine;
use App\Modules\Fiscal\ConsumptionReform\TemporalRateCatalog;
use App\Modules\Fiscal\ConsumptionReform\TransitionCatalog;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function platformCase(array $overrides, string $source = 'regular-saas-2026'): array
{
    $root = dirname(__DIR__, 3).'/resources/fiscal';
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge($root, 'bundles/consumption-phase4b-2026.09.28-4.json');
    $scenario = json_decode((string) file_get_contents($root.'/scenarios/consumption-phase4b/'.$source.'.json'),
        true, 512, JSON_THROW_ON_ERROR);
    $scenario['scenario_id'] = 'platform-case';
    $scenario['knowledge_at'] = '2026-09-28T11:38:00-03:00';
    $scenario['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $scenario['catalog_versions'] = $knowledge['catalog_versions'];
    $scenario['facts'] = array_merge($scenario['facts'], $overrides);
    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($scenario['facts'][$key]);
        }
    }
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($scenario);
    $documents = $knowledge['documents'];
    $engine = new ConsumptionReformEngine(new TemporalRateCatalog($documents['rtc_rate_catalog']),
        $documents['rtc_code_catalog'], new TransitionCatalog($documents['rtc_transition_catalog']));

    return $engine->evaluate($loaded->input->context->facts,
        $loaded->input->context->competenceDate, $loaded->input->context->knowledgeAt);
}

/** @return array<string, mixed> */
function platformBaseFacts(): array
{
    return [
        'rtc.platform_id' => 'platform-1',
        'rtc.platform_remote_intermediation' => true,
        'rtc.platform_controlled_elements' => ['PAYMENT'],
        'rtc.platform_activity_exclusion' => 'NONE',
        'rtc.platform_qualification_evidence_id' => 'platform-contract-1',
        'rtc.platform_reported_operation' => true,
        'rtc.platform_report_evidence_id' => 'authority-report-1',
        'rtc.supplier_document_full_value' => true,
        'rtc.supplier_document_evidence_id' => 'dps-issued-1',
        'rtc.platform_document_registered' => true,
        'rtc.platform_document_evidence_id' => 'platform-document-register-1',
    ];
}

it('distinguishes a qualified platform from an excluded payment-only service', function (): void {
    $missing = platformCase(['rtc.platform_id' => 'platform-1']);
    expect($missing['status'])->toBe('ACTION_REQUIRED')
        ->and($missing['reason_codes'])->toContain('RTC_PLATFORM_QUALIFICATION_UNPROVEN')
        ->and($missing['missing_facts'])->toContain('rtc.platform_controlled_elements');

    $excluded = platformCase([
        'rtc.platform_id' => 'payment-provider-1',
        'rtc.platform_remote_intermediation' => true,
        'rtc.platform_controlled_elements' => ['PAYMENT'],
        'rtc.platform_activity_exclusion' => 'AUTHORIZED_PAYMENT_ONLY',
        'rtc.platform_qualification_evidence_id' => 'bc-license-1',
    ]);
    expect($excluded['status'])->toBe('VALID')
        ->and($excluded['decision']['platform_responsibility']['responsibility'])->toBe('NOT_PLATFORM_UNDER_ART22');

    $search = platformCase([
        'rtc.platform_id' => 'search-provider-1',
        'rtc.platform_remote_intermediation' => true,
        'rtc.platform_controlled_elements' => ['TERMS'],
        'rtc.platform_activity_exclusion' => 'SEARCH_COMPARISON_ONLY',
        'rtc.platform_qualification_evidence_id' => 'search-terms-1',
    ]);
    expect($search['status'])->toBe('ACTION_REQUIRED')
        ->and($search['reason_codes'])->toContain('RTC_PLATFORM_SEARCH_FEE_BASIS_UNPROVEN');
});

it('qualifies domestic solidarity only for evidenced article 22 triggers', function (): void {
    $compliant = platformCase(platformBaseFacts());
    expect($compliant['status'])->toBe('VALID')
        ->and($compliant['decision']['platform_responsibility']['responsibility'])->toBe('NOT_TRIGGERED');

    $omission = platformCase(array_merge(platformBaseFacts(), [
        'rtc.platform_reported_operation' => false,
        'rtc.platform_report_evidence_id' => 'authority-omission-record-1',
    ]));
    expect($omission['status'])->toBe('VALID')
        ->and($omission['decision']['platform_responsibility']['responsibility'])->toBe('DOMESTIC_SUPPLIER_SOLIDARY')
        ->and($omission['decision']['platform_responsibility']['triggers'])->toContain('PLATFORM_INFORMATION_NOT_PROVIDED');

    $undocumented = platformCase(array_merge(platformBaseFacts(), [
        'rtc.supplier_document_full_value' => false,
        'rtc.supplier_is_taxpayer' => true,
        'rtc.supplier_taxpayer_evidence_id' => 'taxpayer-registry-1',
        'rtc.supplier_registered' => true,
    ]));
    expect($undocumented['status'])->toBe('VALID')
        ->and($undocumented['decision']['platform_responsibility']['triggers'])->toContain('SUPPLIER_DOCUMENT_MISSING_OR_UNDERVALUED');
});

it('requires authority notice before assigning the unregistered domestic supplier trigger', function (): void {
    $facts = array_merge(platformBaseFacts(), [
        'rtc.supplier_document_full_value' => false,
        'rtc.supplier_is_taxpayer' => true,
        'rtc.supplier_taxpayer_evidence_id' => 'authority-classification-1',
        'rtc.supplier_registered' => false,
    ]);
    $missing = platformCase($facts);
    expect($missing['status'])->toBe('ACTION_REQUIRED')
        ->and($missing['reason_codes'])->toContain('RTC_PLATFORM_UNREGISTERED_SUPPLIER_NOTICE_MISSING');

    $resolved = platformCase(array_merge($facts, ['rtc.supplier_taxpayer_authority_notice_id' => 'authority-notice-1']));
    expect($resolved['status'])->toBe('VALID')
        ->and($resolved['decision']['platform_responsibility']['responsibility'])->toBe('DOMESTIC_SUPPLIER_SOLIDARY');
});

it('records foreign-supplier platform responsibility without approving the import document', function (): void {
    $result = platformCase(platformBaseFacts(), 'import-digital-2026-document-gap');
    $platformTrace = array_values(array_filter($result['trace'],
        static fn (array $row): bool => ($row['stage'] ?? null) === 'PLATFORM_RESPONSIBILITY'));
    expect($result['status'])->toBe('ACTION_REQUIRED')
        ->and($platformTrace)->toHaveCount(1)
        ->and($platformTrace[0]['responsibility'])->toBe('FOREIGN_SUPPLIER_SUBSTITUTIVE_SOLIDARY')
        ->and($result['reason_codes'])->toContain('RTC_IMPORT_DOCUMENT_OBLIGATION_NOT_PROVEN');
});
