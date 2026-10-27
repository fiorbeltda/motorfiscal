<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\ISS\IssDocumentCurrency140;

require_once __DIR__.'/phase4b_own135_fixture.php';

/** Hypothetical qualified import-consultancy case, not an authenticated taxpayer or legal parameter. */
function issCurrency140Scenario(array $knowledge, string $currency = 'USD'): array
{
    $known = (new DateTimeImmutable(IssDocumentCurrency140::AVAILABLE_AT))->modify('+5 seconds')->format('Y-m-d\TH:i:s.uP');
    $source = $knowledge['documents']['iss_currency_catalog']['quote_source'];
    $value = ['policy_hash' => CanonicalJson::hash(IssDocumentCurrency140::policy()),
        'document_reference' => 'SYNTHETIC-FOREIGN-INVOICE-140', 'supplier_id' => 'SYNTHETIC-US-SUPPLIER-140',
        'customer_id' => '11111111000191', 'municipality' => '3550308', 'lc116_code' => '17.01',
        'iss_supply_on' => '2026-09-25', 'supply_event_kind' => 'FINAL_REPORT_DELIVERY',
        'supply_event_reference' => 'SYNTHETIC-FINAL-REPORT-DELIVERY-140',
        'original_amount' => '1000.00', 'original_currency' => $currency,
        'converted_price_brl' => $currency === 'BRL' ? '1000.00' : '5199.10',
        'original_operation_amount_brl' => '5179.50',
        'quote_source_id' => $currency === 'BRL' ? null : $source['source_id'],
        'quote_source_sha256' => $currency === 'BRL' ? null : $source['sha256'],
        'quote_date' => $currency === 'BRL' ? null : '2026-09-25', 'rate' => $currency === 'BRL' ? null : '5.19910',
        'qualified_scope' => IssDocumentCurrency140::policy()['operation_scope']];
    $proof = own135Capture(['contract' => IssDocumentCurrency140::CONTRACT, 'evidence_id' => 'SYNTHETIC-ISS-CURRENCY140',
        'observed_at' => $known, 'valid_from' => '2026-01-01', 'valid_to' => '2026-12-31',
        'revoked_at' => null, 'superseded_at' => null, 'value' => $value]);

    return ['scenario_id' => 'ISS-CURRENCY140-'.$currency, 'rule_bundle_id' => $knowledge['rule_bundle_id'],
        'catalog_versions' => $knowledge['catalog_versions'], 'competence_date' => '2026-09-25', 'knowledge_at' => $known,
        'facts' => ['service.canonical_id' => 'CTRIBNAC_170101', 'operation.service_origin' => 'FOREIGN',
            'operation.issuer_role' => 'CUSTOMER', 'customer.municipality_ibge' => '3550308', 'customer.person_type' => 'PJ',
            'provider.person_type' => 'FOREIGN_LEGAL_ENTITY', 'provider.tax_regime' => 'NOT_APPLICABLE_FOREIGN',
            'operation.amount' => '5179.50', 'operation.withholding_performed' => true,
            'invoice.issue_date' => '2026-10-01', 'payment.date' => '2026-10-20',
            'federal.evaluate_withholdings' => false, 'rtc.evaluate' => false,
            'iss.original_price_amount' => '1000.00', 'iss.original_price_currency' => $currency,
            'iss.document_supply_on' => '2026-09-25', 'iss.document_reference' => $value['document_reference'],
            'iss.document_supplier_id' => $value['supplier_id'], 'iss.document_customer_id' => $value['customer_id'],
            'iss.supply_event_kind' => 'FINAL_REPORT_DELIVERY', 'iss.supply_event_reference' => $value['supply_event_reference'],
            IssDocumentCurrency140::PROOFS => [$proof]]];
}
