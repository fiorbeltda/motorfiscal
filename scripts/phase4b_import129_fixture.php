<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

/** Shared synthetic operation fixture; no historical scenario is rewritten.
 * @param  array<string, mixed>  $overrides
 * @return array{array<string, mixed>, array<string, mixed>}
 */
function phase4bImport129Fixture(FiscalEngineFactory $factory, array $overrides = [], bool $recipient = false, string $version = '129'): array
{
    $knowledge = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.22-'.$version.'-draft1.json');
    $source = json_decode((string) file_get_contents(resource_path('fiscal/scenarios/consumption-phase4b-2026.09.28-15/import-credit-proven-2026.json')), true, 512, JSON_THROW_ON_ERROR);
    $source['scenario_id'] = 'IMPORT129-SYNTHETIC-'.($recipient ? 'RECIPIENT' : 'ACQUIRER');
    $source['rule_bundle_id'] = $knowledge['rule_bundle_id'];
    $source['catalog_versions'] = $knowledge['catalog_versions'];
    $source['knowledge_at'] = '2026-10-22T16:45:00+00:00';
    $source['facts'] = [...$source['facts'], 'operation.service_origin' => 'FOREIGN',
        'operation.issuer_role' => 'CUSTOMER', 'provider.person_type' => 'FOREIGN_LEGAL_ENTITY',
        'customer.municipality_ibge' => '3550308', 'rtc.presumed_credit_requested' => false,
        'rtc.input_tax_treatments' => array_map(static fn (string $tax): array => ['tax' => $tax,
            'treatment' => 'STANDARD', 'document_id' => 'dfe-import-1',
            'evidence_id' => 'SYNTHETIC-IMPORT129-'.$tax], ['IBS', 'CBS'])];
    unset($source['facts']['provider.municipality_ibge']);
    if ($recipient) {
        $source['facts'] = [...$source['facts'], 'rtc.acquirer_country' => 'CA', 'rtc.acquirer_id' => 'CA-ACQUIRER-129',
            'rtc.destination_basis' => 'RECIPIENT_DOMICILE', 'rtc.collection_method' => 'RECIPIENT',
            'rtc.collection_legal_basis' => 'LC214-ART64-VI'];
    }
    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($source['facts'][$key]);
        } else {
            $source['facts'][$key] = $value;
        }
    }

    return [$knowledge, $source];
}
