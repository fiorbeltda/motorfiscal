<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SoftwareOwnRevenue135;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\OwnFederalValues136;
use App\Modules\Fiscal\NFSe\PartialExportDpsPair136;

require_once __DIR__.'/phase4b_dps_pair135_fixture.php';

/** Synthetic explicit classification and retention claims;not taxpayer authentication. */
function own136Proofs(array $iss, array $ownProofs, DateTimeImmutable $known): array
{
    $own = (new SoftwareOwnRevenue135)->calculate($iss, $ownProofs, SoftwareOwnRevenue135::policy(), $known);
    if ($own['status'] !== 'LOCAL_SOFTWARE_OWN_REVENUE_LINES_CALCULATED') {
        throw new RuntimeException('Own136 fixture requires a fresh own calculation.');
    }
    $proofs = [];
    foreach ($own['documents'] as $line) {
        $role = $line['role'];
        $export = $line['own_contribution_treatment'] === 'SERVICE_EXPORT_WITH_DIVISAS';
        $prior = array_values(array_filter($ownProofs, static fn (array $p): bool => $p['value']['document']['role'] === $role))[0];
        $proofs[] = own135Capture(['contract' => OwnFederalValues136::CONTRACT, 'evidence_id' => 'SYNTHETIC-FEDERAL136-'.$role,
            'observed_at' => $known->format('Y-m-d\TH:i:s.uP'), 'valid_from' => '2026-01-01', 'valid_to' => '2026-12-31',
            'revoked_at' => null, 'superseded_at' => null,
            'value' => ['source_policy_hash' => CanonicalJson::hash(OwnFederalValues136::policy()), 'document' => $prior['value']['document'],
                'own_revenue_result_hash' => CanonicalJson::hash($own), 'own_proof_hash' => $line['proof_hash'],
                'selection' => ['pis_cst' => $export ? '08' : '01', 'cofins_cst' => $export ? '08' : '01',
                    'export_receipt_code' => $export ? '402' : null, 'review_reference' => 'SYNTHETIC-REVIEW-'.$role],
                'withholding' => ['PIS' => 'NOT_WITHHELD', 'COFINS' => 'NOT_WITHHELD', 'CSLL' => 'NOT_WITHHELD',
                    'evidence_reference' => 'SYNTHETIC-THREE-TAX-RETENTION-STATUS-'.$role]]]);
    }

    return $proofs;
}

function own136Fixture(): array
{
    $known = new DateTimeImmutable(PartialExportDpsPair136::AVAILABLE_AT);
    $issued = $known->format('Y-m-d\TH:i:sP');
    $case = own135PairFixture(knowledgeAt: $known->format('Y-m-d\TH:i:s.uP'), identityOverrides: [
        'RTC_FOREIGN' => ['issued_at' => $issued, 'series' => '1', 'number' => '1363'],
        'RTC_DOMESTIC' => ['issued_at' => $issued, 'series' => '1', 'number' => '1364']]);
    $base = pair135Fixture();
    $decl = $base['declarations'];
    $decl['own_tax_proofs'] = $case['own_proofs'];
    $byRole = array_column($case['iss']['documents'], null, 'role');
    foreach ($decl['comex_proofs'] as &$proof) {
        $proof['observed_at'] = $known->format('Y-m-d\TH:i:s.uP');
        $document = $byRole[$proof['value']['role']];
        foreach (['document_reference', 'contract_reference', 'unit_reference', 'line_reference', 'iss_supply_on'] as $field) {
            $proof['value'][$field] = $document[$field];
        }
        $proof['value']['fresh_iss_binding_hash'] = $case['iss']['binding_hash'];
        $proof = own135Capture($proof);
    }
    unset($proof);
    $decl['documentary_proof']['observed_at'] = $known->format('Y-m-d\TH:i:s.uP');
    $decl['documentary_proof']['value']['options']['issued_at'] = $issued;
    $decl['documentary_proof']['value']['options']['numbers'] = ['RTC_FOREIGN' => '1363', 'RTC_DOMESTIC' => '1364'];
    $decl = pair135Reseal($case['snapshot'], $decl);
    $decl['own_federal_proofs'] = own136Proofs($case['iss'], $case['own_proofs'], $known);

    return [...$case, 'declarations' => $decl];
}
