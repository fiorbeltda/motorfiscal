<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DocumentComex135;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\NFSe\PartialExportDpsPair135;

require_once __DIR__.'/phase4b_own135_pair_fixture.php';
require_once __DIR__.'/phase4b_iss134_fixture.php';

/** Capture/reenvelope synthetic claims only; does not change snapshot result or monetary inputs. */
function pair135Reseal(array $snapshot, array $declarations): array
{
    $proof = $declarations['documentary_proof'];
    $ownHashes = [];
    foreach ($declarations['own_tax_proofs'] as $own) {
        $ownHashes[$own['value']['document']['role']] = $own['source_hash'];
    }
    $tradeHashes = [];
    foreach ($declarations['comex_proofs'] as $trade) {
        $tradeHashes[$trade['value']['role']] = $trade['source_hash'];
    }
    ksort($ownHashes);
    ksort($tradeHashes);
    $proof['value'] = ['projection_policy_hash' => CanonicalJson::hash(PartialExportDpsPair135::policy()),
        'snapshot_hash' => CanonicalJson::hash($snapshot), 'own_proof_hashes' => $ownHashes,
        'comex_proof_hashes' => $tradeHashes, 'options' => $proof['value']['options']];
    $declarations['documentary_proof'] = own135Capture($proof);

    return $declarations;
}

function pair135Fixture(string $domesticPrice = '641.33'): array
{
    $issued = PartialExportDpsPair135::AVAILABLE_AT;
    $known = (new DateTimeImmutable($issued))->format('Y-m-d\TH:i:s.uP');
    $case = own135PairFixture($domesticPrice, knowledgeAt: $known, identityOverrides: [
        'RTC_FOREIGN' => ['issued_at' => $issued, 'series' => '1', 'number' => '1353'],
        'RTC_DOMESTIC' => ['issued_at' => $issued, 'series' => '1', 'number' => '1354']]);
    $snapshot = $case['snapshot'];
    $iss = $case['iss'];
    $facts = $snapshot['input']['facts'];
    $comex = [];
    $trades = [];
    foreach ($iss['documents'] as $document) {
        $role = $document['role'];
        $country = $role === 'RTC_FOREIGN' ? 'US' : 'BR'; // Explicit synthetic performance claim, not a projector inference.
        $trade = ['mdPrestacao' => $country === 'BR' ? '2' : '1', 'vincPrest' => '0', 'tpMoeda' => '790',
            'vServMoeda' => $document['gross_service_price'], 'mecAFComexP' => '01', 'mecAFComexT' => '01', 'movTempBens' => '1', 'mdic' => '0'];
        $trades[$role] = $trade;
        $comex[] = own135Capture(['contract' => DocumentComex135::CONTRACT, 'evidence_id' => 'SYNTHETIC-PAIR135-COMEX-'.$role,
            'observed_at' => $known, 'valid_from' => '2026-01-01', 'valid_to' => '2026-12-31', 'revoked_at' => null, 'superseded_at' => null,
            'value' => ['role' => $role, 'document_reference' => $document['document_reference'], 'contract_reference' => $document['contract_reference'],
                'unit_reference' => $document['unit_reference'], 'line_reference' => $document['line_reference'], 'iss_supply_on' => $document['iss_supply_on'],
                'supplier_id' => $facts['rtc.supplier_id'], 'acquirer_id' => $facts['rtc.acquirer_id'], 'fresh_iss_binding_hash' => $iss['binding_hash'],
                'declared_relationship' => 'NO_RELATIONSHIP', 'declared_support_mechanisms' => 'NONE_FOR_BOTH_PARTIES',
                'temporary_goods_movement' => false, 'mdic_information_authorized' => false, 'trade_fields' => $trade,
                'performance_claim' => ['execution_country' => 'BR', 'consumer_presence_country' => $country,
                    'temporary_people_movement' => false, 'evidence_reference' => 'SYNTHETIC-PERFORMANCE-'.$role]]]);
    }
    $options = ['issued_at' => $issued, 'issuer_municipality' => '3550308', 'service_municipality' => '3550308', 'series' => '1',
        'numbers' => ['RTC_FOREIGN' => '1353', 'RTC_DOMESTIC' => '1354'], 'simples_option' => '1', 'special_regime' => '0',
        'nbs' => '115022000', 'software_type' => 'CUSTOMIZED', 'nbs_review_evidence_id' => 'SYNTHETIC-NBS-CUSTOMIZED-135',
        'service_description' => 'Synthetic customized software development & implementation', 'domestic_recipient_name' => 'Synthetic domestic recipient',
        'foreign_acquirer_internal_id' => $facts['rtc.acquirer_id'], 'domestic_recipient_internal_id' => $facts['rtc.export_domestic_recipient_id'],
        'foreign_acquirer' => ['country' => 'US', 'name' => 'Foreign & Company', 'nif' => 'NIF-135',
            'address' => ['postal_code' => '10001', 'city' => 'New York', 'province' => 'NY', 'street' => 'Main & Second', 'number' => '10', 'district' => 'Manhattan'],
            'party_evidence_id' => 'SYNTHETIC-PARTY-135', 'identification_evidence_id' => 'SYNTHETIC-ID-135', 'address_evidence_id' => 'SYNTHETIC-ADDRESS-135'],
        'values_options' => phase4bIss134Options($snapshot), 'foreign_trade' => $trades];
    $declarations = ['own_tax_proofs' => $case['own_proofs'], 'comex_proofs' => $comex,
        'documentary_proof' => ['contract' => PartialExportDpsPair135::CONTRACT, 'evidence_id' => 'SYNTHETIC-PAIR135-ENVELOPE',
            'observed_at' => $known, 'valid_from' => '2026-01-01', 'valid_to' => '2026-12-31', 'revoked_at' => null, 'superseded_at' => null,
            'value' => ['options' => $options]]];

    return ['snapshot' => $snapshot, 'declarations' => pair135Reseal($snapshot, $declarations)];
}
