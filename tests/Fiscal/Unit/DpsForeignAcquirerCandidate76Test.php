<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Schema\DpsXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DpsForeignAcquirerCandidate;
use App\Modules\Fiscal\NFSe\DpsRestrictedSchemaSelection;
use App\Modules\Fiscal\NFSe\DpsXmlProjector;

function dpsForeignAcquirer76Fixture(): array
{
    $root = dirname(__DIR__, 3);
    $snapshot = json_decode((string) file_get_contents($root.'/storage/app/phase4b-international76-gap-20261015/foreign-buyer-domestic-consumption-snapshot.json'), true, 512, JSON_THROW_ON_ERROR);
    $identity = json_decode((string) file_get_contents($root.'/storage/app/phase4b-international76-gap-20261015/foreign-buyer-domestic-consumption-identity.json'), true, 512, JSON_THROW_ON_ERROR);
    $codes = json_decode((string) file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-2026.10.14-74-draft1.json'), true, 512, JSON_THROW_ON_ERROR);
    $codes['local_xml_projection']['contract_version'] = DpsForeignAcquirerCandidate::CONTRACT;
    $codes['foreign_acquirer_candidate'] = ['scope' => 'LOCAL_STRUCTURAL_CANDIDATE_ONLY',
        'currency_competence_validity_proven' => false,
        'currency_code_source_sha256' => '4ef4ecd5574dd880f6ab49fcf20eaaefa8c6084ff2ca3a6463e02dd1c1478f1c',
        'currency_code_observed_at' => '2026-10-14T19:42:38.923982+00:00',
        'observed_currency_codes' => ['BRL' => '790', 'USD' => '220', 'EUR' => '978', 'AUD' => '150']];
    $identity['acquirer_name'] = 'Foreign & Company';
    $identity['foreign_acquirer_id'] = 'US-CUSTOMER-1';
    $identity['foreign_document_id'] = 'dps-1';
    $identity['foreign_acquirer'] = ['country' => 'US', 'name' => 'Foreign & Company', 'nif' => 'NIF-76',
        'address' => ['postal_code' => '10001', 'city' => 'New York', 'province' => 'NY',
            'street' => 'Main & Second', 'number' => '10', 'district' => 'Manhattan'],
        'party_evidence_id' => 'SYNTHETIC.PARTY.76', 'identification_evidence_id' => 'SYNTHETIC.ID.76',
        'address_evidence_id' => 'SYNTHETIC.ADDRESS.76'];
    $identity['foreign_trade'] = ['mdPrestacao' => '2', 'vincPrest' => '0', 'tpMoeda' => '790',
        'vServMoeda' => '1000.00', 'mecAFComexP' => '01', 'mecAFComexT' => '01', 'movTempBens' => '1', 'mdic' => '0'];
    $identity['foreign_trade_evidence_id'] = 'SYNTHETIC.COMEXT.76';

    return [$snapshot['result']['decision'], $identity, $codes];
}

it('projects a snapshot-bound foreign taker and domestic recipient without changing the fiscal decision', function (): void {
    [$decision, $identity, $codes] = dpsForeignAcquirer76Fixture();
    $before = $decision;
    $projector = new DpsXmlProjector;
    $result = $projector->project($decision, $identity, $codes);
    expect($result['status'])->toBe('PROJECTED')->and($decision)->toBe($before)
        ->and($result)->toBe($projector->project($decision, $identity, $codes))
        ->and($result['xml'])->toContain('<NIF>NIF-76</NIF>', '<endExt>', '<comExt>', '<tpMoeda>790</tpMoeda>', '<vServMoeda>1000.00</vServMoeda>')
        ->and($result['foreign_acquirer_binding']['document_obligation_complete'])->toBeFalse()
        ->and($result['foreign_acquirer_binding']['currency_competence_validity_proven'])->toBeFalse()
        ->and($result['foreign_acquirer_binding']['production_ready'])->toBeFalse();
    $root = dirname(__DIR__, 3);
    $validation = (new DpsXmlSchemaValidator)->validate($result['xml'],
        $root.'/resources/fiscal/sources/official/'.$codes['dps_xsd']['source_file'],
        DpsRestrictedSchemaSelection::ARCHIVE_SHA256, 'DPS_v1.01.xsd');
    expect($validation['status'])->toBe('VALID')->and($validation['errors'])->toBe([]);
});

it('does not enable the foreign route through a legacy call or the earlier restricted contract', function (): void {
    [$decision, $identity, $codes] = dpsForeignAcquirer76Fixture();
    $projector = new DpsXmlProjector;
    expect($projector->project($decision, $identity)['reason'])->toBe('RTC_DPS_ACQUIRER_IDENTITY_REQUIRED');
    $codes['local_xml_projection']['contract_version'] = DpsRestrictedSchemaSelection::CONTRACT;
    expect($projector->project($decision, $identity, $codes)['reason'])->toBe('RTC_DPS_FOREIGN_ACQUIRER_CONTRACT_NOT_PROVEN');
});

it('refuses international party trade and environment conflicts instead of producing a misleading XML', function (string $case): void {
    [$decision, $identity, $codes] = dpsForeignAcquirer76Fixture();
    match ($case) {
        'party-id' => $identity['foreign_acquirer_id'] = 'OTHER',
        'document' => $identity['foreign_document_id'] = 'OTHER',
        'country' => $identity['foreign_acquirer']['country'] = 'DE',
        'name' => $identity['foreign_acquirer']['name'] = 'Other name',
        'production' => $identity['environment'] = '1',
        'missing-party' => $identity = array_diff_key($identity, ['foreign_acquirer' => true]),
        'missing-trade' => $identity = array_diff_key($identity, ['foreign_trade' => true]),
        'missing-evidence' => $identity['foreign_trade_evidence_id'] = '',
        'wrong-currency' => $identity['foreign_trade']['tpMoeda'] = '220',
        'wrong-value' => $identity['foreign_trade']['vServMoeda'] = '999.99',
        'missing-field' => $identity['foreign_trade'] = array_diff_key($identity['foreign_trade'], ['mdic' => true]),
        'future-mode' => $identity['foreign_trade']['mdPrestacao'] = '5',
        'new-field' => $identity['foreign_trade']['nFatura'] = '1234',
        'goods-without-document' => $identity['foreign_trade']['movTempBens'] = '2',
        'unexpected-goods-document' => $identity['foreign_trade']['nDI'] = '123456789012345',
        'no-source-hash' => $codes['foreign_acquirer_candidate']['currency_code_source_sha256'] = '',
        'pre-observation-issuance' => $identity['issued_at'] = '2026-10-14T15:00:00+00:00',
        'invalid-observation' => $codes['foreign_acquirer_candidate']['currency_code_observed_at'] = '2026-02-30T19:42:38.923982+00:00',
    };
    $result = (new DpsXmlProjector)->project($decision, $identity, $codes);
    expect($result['status'])->not->toBe('PROJECTED')->and($result['xml'])->toBeNull();
})->with(['party-id', 'document', 'country', 'name', 'production', 'missing-party', 'missing-trade',
    'missing-evidence', 'wrong-currency', 'wrong-value', 'missing-field', 'future-mode', 'new-field',
    'goods-without-document', 'unexpected-goods-document', 'no-source-hash', 'pre-observation-issuance', 'invalid-observation']);
