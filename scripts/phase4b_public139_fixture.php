<?php

declare(strict_types=1);

/** Synthetic public-payment facts; local rules are fixtures, not approved parameters. */
function public139Input(array $knowledge, array $overrides = []): array
{
    return [
        'scenario_id' => 'PUBLIC139-SYNTHETIC-PAYMENT',
        'rule_bundle_id' => $knowledge['rule_bundle_id'],
        'catalog_versions' => $knowledge['catalog_versions'],
        'knowledge_at' => '2026-10-26T19:15:00+00:00',
        'competence_date' => '2026-10-26',
        'facts' => array_replace([
            'service.canonical_id' => 'SOFTWARE_DEVELOPMENT',
            'provider.municipality_ibge' => '3550308',
            'customer.municipality_ibge' => '3509502',
            'customer.person_type' => 'PJ',
            'operation.service_origin' => 'DOMESTIC',
            'operation.amount' => '641.33',
            'rtc.evaluate' => false,
            'rtc.supplier_id' => '11111111000191',
            'federal.evaluate_withholdings' => true,
            'provider.person_type' => 'PRIVATE_LEGAL_ENTITY',
            'provider.tax_regime' => 'LUCRO_REAL',
            'provider.head_office_tax_id' => '11111111000191',
            'payer.person_type' => 'FEDERAL_PUBLIC_ADMINISTRATION',
            'payer.tax_regime' => 'PUBLIC_ENTITY',
            'payer.public_entity_type' => 'DIRECT',
            'payer.head_office_tax_id' => '33333333000191',
            'payer.siafi_electronic_collection' => false,
            'payment.occurred' => true,
            'payment.date' => '2026-10-26',
            'payment.gross_amount' => '641.33',
            'invoice.issue_date' => '2026-10-26',
            'invoice.gross_amount' => '641.33',
            'federal.service_category' => 'PROFESSIONAL_SERVICE',
            'federal.income_nature_code' => '17001',
            'federal.pis_status' => 'APPLICABLE',
            'federal.cofins_status' => 'APPLICABLE',
            'federal.csll_status' => 'APPLICABLE',
            'inss.service_group' => 'OUTSIDE_EXHAUSTIVE_LIST',
            'inss.service_category' => 'SOFTWARE_DEVELOPMENT',
            'inss.execution_mode' => 'CONTRACT_FOR_RESULT',
            'public.annex_category' => 'GENERAL_SERVICES',
            'public.glosa_without_new_invoice' => false,
            'public.has_late_additions' => false,
            'public.beneficiary_treatment' => 'STANDARD',
            'public.article33_convention_exists' => false,
            'public.local_ir_collection_rule_id' => 'SYNTHETIC-LOCAL-IR-RULE-139',
            'public.local_ir_collection_code' => 'SYNTHETIC-LOCAL-IR-139',
            'public.local_ir_due_rule' => 'SYNTHETIC-LOCAL-DUE-RULE-139',
            'public.local_ir_valid_from' => '2026-01-01',
            'public.local_ir_valid_to' => '2026-12-31',
        ], $overrides),
    ];
}
