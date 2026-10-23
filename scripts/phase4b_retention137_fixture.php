<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\DocumentFederalPayment137;
use App\Modules\Fiscal\NFSe\OwnContributionFields137;
use App\Modules\Fiscal\NFSe\PartialExportDpsPair135;

require_once __DIR__.'/phase4b_dps_pair135_fixture.php';

function payment137Bank(array $value): array
{
    $p = $value['payment'];
    $record = ['document_reference' => $value['document']['document_reference'], 'payment_reference' => $p['reference'],
        'payer_id' => $p['payer_id'], 'payer_country' => $p['payer_country'], 'payee_id' => $p['payee_id'], 'currency' => $p['currency'],
        'gross_brl' => $p['gross_brl'], 'net_brl' => $p['net_brl'], 'settled_on' => $p['settled_on']];
    $content = CanonicalJson::encode($record);

    return ['reference' => 'SYNTHETIC-BANK-STATEMENT-137', 'media_type' => 'application/json', 'content' => $content, 'content_sha256' => hash('sha256', $content)];
}

function retention137Fixture(): array
{
    $known = (new DateTimeImmutable(DocumentFederalPayment137::AVAILABLE_AT))->modify('+5 seconds');
    $issued = DocumentFederalPayment137::AVAILABLE_AT;
    $case = own135PairFixture(knowledgeAt: $known->format('Y-m-d\TH:i:s.uP'), identityOverrides: [
        'RTC_FOREIGN' => ['issued_at' => $issued, 'series' => '1', 'number' => '1373'],
        'RTC_DOMESTIC' => ['issued_at' => $issued, 'series' => '1', 'number' => '1374']]);
    // Synthetic declarations must use the country of their bound customer,not the RTC consumption role.
    foreach ($case['own_proofs'] as &$p) {
        $p['value']['revenue']['customer_country'] = $case['snapshot']['input']['facts']['rtc.acquirer_country'];
        $p = own135Capture($p);
    }unset($p);
    $base = pair135Fixture();
    $decl = $base['declarations'];
    $decl['own_tax_proofs'] = $case['own_proofs'];
    $docs = array_column($case['iss']['documents'], null, 'role');
    foreach ($decl['comex_proofs'] as &$p) {
        $p['observed_at'] = $known->format('Y-m-d\TH:i:s.uP');
        $doc = $docs[$p['value']['role']];
        foreach (['document_reference', 'contract_reference', 'unit_reference', 'line_reference', 'iss_supply_on'] as $key) {
            $p['value'][$key] = $doc[$key];
        }
        $p['value']['fresh_iss_binding_hash'] = $case['iss']['binding_hash'];
        $p = own135Capture($p);
    }
    unset($p);
    $decl['documentary_proof']['observed_at'] = $known->format('Y-m-d\TH:i:s.uP');
    $decl['documentary_proof']['value']['options']['issued_at'] = $issued;
    $decl['documentary_proof']['value']['options']['numbers'] = ['RTC_FOREIGN' => '1373', 'RTC_DOMESTIC' => '1374'];
    $decl = pair135Reseal($case['snapshot'], $decl);
    $raw = $case['snapshot']['input']['facts'];
    $policy = DocumentFederalPayment137::policy();
    $proofs = [];

    foreach ($case['own_proofs'] as $own) {
        $context = $own['value']['document'];
        $foreign = $context['role'] === 'RTC_FOREIGN';
        $ownBank = $foreign ? json_decode($own['value']['revenue']['bank_inflow']['content'], true, flags: JSON_THROW_ON_ERROR) : null;
        $payment = ['reference' => 'SYNTHETIC-SEPARATE-SETTLEMENT-'.$context['role'], 'currency' => 'BRL',
            'gross_brl' => $context['composed_price_brl'], 'net_brl' => $foreign ? '400.00' : '611.51',
            'settled_on' => $foreign ? $ownBank['settled_on'] : $known->format('Y-m-d'),
            'payer_role' => $foreign ? 'ACQUIRER' : 'DOMESTIC_RECIPIENT',
            'payer_id' => $foreign ? $raw['rtc.acquirer_id'] : $raw['rtc.export_domestic_recipient_id'],
            'payer_country' => $foreign ? 'US' : 'BR', 'payer_person_type' => $foreign ? 'FOREIGN' : 'PRIVATE_LEGAL_ENTITY',
            'payee_id' => $context['supplier_id'], 'bank' => null,
            'payer_accounting_credit' => ['status' => 'NO_PAYER_ACCOUNTING_CREDIT', 'credited_on' => null, 'evidence_reference' => 'SYNTHETIC-PAYER-CREDIT-REVIEW-137']];
        $value = ['source_policy_hash' => CanonicalJson::hash($policy), 'snapshot_hash' => CanonicalJson::hash($case['snapshot']),
            'documentary_proof_hash' => $decl['documentary_proof']['source_hash'], 'document' => $context, 'payment' => $payment,
            'profile' => ['provider_tax_regime' => 'LUCRO_REAL', 'payer_tax_regime' => $foreign ? 'NOT_APPLICABLE' : 'LUCRO_REAL',
                'provider_head_office_tax_id' => '11111111000191', 'payer_head_office_tax_id' => $foreign ? null : '33333333000191',
                'head_office_evidence_reference' => 'SYNTHETIC-CAPTURED-HEAD-OFFICE-RELATIONSHIPS-137'],
            'federal_facts' => ['federal.service_category' => 'PROFESSIONAL_SERVICE', 'federal.pis_status' => 'APPLICABLE',
                'federal.cofins_status' => 'APPLICABLE', 'federal.csll_status' => 'APPLICABLE', 'federal.income_nature_code' => '17001',
                'inss.service_group' => 'OUTSIDE_EXHAUSTIVE_LIST', 'inss.service_category' => 'SOFTWARE_DEVELOPMENT', 'inss.execution_mode' => 'CONTRACT_FOR_RESULT']];
        $value['payment']['bank'] = payment137Bank($value);
        $proofs[] = own135Capture(['contract' => DocumentFederalPayment137::CONTRACT, 'evidence_id' => 'SYNTHETIC-PAYMENT137-'.$context['role'],
            'observed_at' => $known->format('Y-m-d\TH:i:s.uP'), 'valid_from' => '2026-01-01', 'valid_to' => '2026-12-31',
            'revoked_at' => null, 'superseded_at' => null, 'value' => $value]);
    }
    $prior = (new PartialExportDpsPair135)->project($case['snapshot'], $decl, resource_path('fiscal'));
    $ownResult = $prior['bridge']['fresh_own_calculation'];
    $selections = [];
    foreach ($ownResult['documents'] as $line) {
        $export = $line['own_contribution_treatment'] === 'SERVICE_EXPORT_WITH_DIVISAS';
        $proof = array_values(array_filter($decl['own_tax_proofs'], fn ($p) => $p['source_hash'] === $line['proof_hash']))[0];
        $selections[] = own135Capture(['contract' => OwnContributionFields137::CONTRACT,
            'evidence_id' => 'SYNTHETIC-OWN-SELECTION137-'.$line['role'], 'observed_at' => $known->format('Y-m-d\TH:i:s.uP'),
            'valid_from' => '2026-01-01', 'valid_to' => '2026-12-31', 'revoked_at' => null, 'superseded_at' => null,
            'value' => ['source_policy_hash' => CanonicalJson::hash(OwnContributionFields137::policy()),
                'document' => $proof['value']['document'], 'own_revenue_result_hash' => CanonicalJson::hash($ownResult),
                'own_proof_hash' => $line['proof_hash'], 'selection' => ['pis_cst' => $export ? '08' : '01', 'cofins_cst' => $export ? '08' : '01',
                    'export_receipt_code' => $export ? '402' : null, 'review_reference' => 'SYNTHETIC-OWN-CST-REVIEW137']]]);
    }

    return [...$case, 'declarations' => [...$decl, 'payment_proofs' => $proofs, 'own_selection_proofs' => $selections]];
}
