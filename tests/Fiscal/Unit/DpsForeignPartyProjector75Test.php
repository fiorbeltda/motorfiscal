<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Schema\DpsXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DpsForeignPartyProjector;
use App\Modules\Fiscal\NFSe\DpsRestrictedSchemaSelection;

function dpsForeignParty75Fixture(): array
{
    return ['country' => 'US', 'name' => 'Foreign & Company', 'nif' => 'NIF-75',
        'address' => ['postal_code' => '10001', 'city' => 'New York', 'province' => 'NY',
            'street' => 'Main & Second', 'number' => '10', 'complement' => 'Floor 2', 'district' => 'Manhattan'],
        'party_evidence_id' => 'SYNTHETIC.PARTY.75', 'identification_evidence_id' => 'SYNTHETIC.ID.75',
        'address_evidence_id' => 'SYNTHETIC.ADDRESS.75'];
}

beforeEach(function (): void {
    $this->foreignCodes75 = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/resources/fiscal/catalogs/rtc-codes-2026.10.14-74-draft1.json'), true, 512, JSON_THROW_ON_ERROR);
});

it('builds deterministic foreign party candidates with exactly one identification choice', function (string $role, string $choice): void {
    $party = dpsForeignParty75Fixture();
    if ($choice !== 'NIF') {
        unset($party['nif']);
        $party['no_nif_reason'] = $choice;
    }
    $projector = new DpsForeignPartyProjector;
    $result = $projector->project($party, $role, $this->foreignCodes75);
    expect($result['status'])->toBe('PROJECTED')
        ->and($result)->toBe($projector->project($party, $role, $this->foreignCodes75))
        ->and($result['xml'])->toContain('Foreign &amp; Company', 'Main &amp; Second', '<endExt>')
        ->and($result['xml'])->not->toContain('<CNPJ>', '<CPF>', '<endNac>')
        ->and($result['country_list_qualified'])->toBeFalse()
        ->and($result['identity_authenticity_verified'])->toBeFalse()
        ->and($result['residency_verified'])->toBeFalse()
        ->and($result['document_obligation_qualified'])->toBeFalse()
        ->and($result['production_ready'])->toBeFalse();
    expect($result['fields'][$role.'.'.($choice === 'NIF' ? 'NIF' : 'cNaoNIF')])->toBe($choice === 'NIF' ? 'NIF-75' : $choice);
    $root = dirname(__DIR__, 3);
    $base = (string) file_get_contents($root.'/storage/app/phase4b-schema74-cli-corpus-v2-20261014/federal-1.xml');
    expect(hash('sha256', $base))->toBe('d52c3b2a3709370de2d7836f307c7883b873e4836e3e84d761edfd1c19a47270');
    $document = new DOMDocument;
    $fragment = new DOMDocument;
    $document->loadXML($base, LIBXML_NONET);
    $fragment->loadXML($result['xml'], LIBXML_NONET);
    $query = new DOMXPath($document);
    $query->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');
    $foreign = $document->importNode($fragment->documentElement, true);
    if ($role === 'toma') {
        $old = $query->query('/n:DPS/n:infDPS/n:toma')->item(0);
        $old->parentNode->replaceChild($foreign, $old);
    } else {
        $query->query('/n:DPS/n:infDPS/n:IBSCBS/n:indDest')->item(0)->textContent = '1';
        $rtc = $query->query('/n:DPS/n:infDPS/n:IBSCBS')->item(0);
        $values = $query->query('/n:DPS/n:infDPS/n:IBSCBS/n:valores')->item(0);
        $rtc->insertBefore($foreign, $values);
    }
    $validation = (new DpsXmlSchemaValidator)->validate((string) $document->saveXML(),
        $root.'/resources/fiscal/sources/official/'.$this->foreignCodes75['dps_xsd']['source_file'],
        DpsRestrictedSchemaSelection::ARCHIVE_SHA256, 'DPS_v1.01.xsd');
    expect($validation['status'])->toBe('VALID')->and($validation['errors'])->toBe([]);
})->with(['toma', 'IBSCBS.dest'])->with(['NIF', '1', '2']);

it('refuses ambiguous unrepresentable incomplete or silently discarded foreign data', function (string $case): void {
    $party = dpsForeignParty75Fixture();
    match ($case) {
        'both' => $party['no_nif_reason'] = '1',
        'neither' => $party = array_diff_key($party, ['nif' => true]),
        'origin-unknown' => $party = [...array_diff_key($party, ['nif' => true]), 'no_nif_reason' => '0'],
        'unknown-reason' => $party = [...array_diff_key($party, ['nif' => true]), 'no_nif_reason' => '3'],
        'integer-reason' => $party = [...array_diff_key($party, ['nif' => true]), 'no_nif_reason' => 1],
        'domestic' => $party['country'] = 'BR',
        'lowercase' => $party['country'] = 'us',
        'country-space' => $party['country'] = 'US ',
        'leading-street' => $party['address']['street'] = ' Main',
        'unsupported-street' => $party['address']['street'] = '道',
        'long-nif' => $party['nif'] = str_repeat('N', 41),
        'blank-nif' => $party['nif'] = ' ',
        'missing-postal' => $party['address'] = array_diff_key($party['address'], ['postal_code' => true]),
        'long-postal' => $party['address']['postal_code'] = str_repeat('1', 12),
        'integer-postal' => $party['address']['postal_code'] = 10001,
        'xml-control' => $party['nif'] = "ID\0END",
        'national-address' => $party['address']['CEP'] = '01001000',
        'cpf-substitution' => $party['cpf'] = '12345678901',
        'missing-evidence' => $party['identification_evidence_id'] = '',
        'unexpected-contact' => $party['fax'] = '12125550100',
    };
    $result = (new DpsForeignPartyProjector)->project($party, 'toma', $this->foreignCodes75);
    expect($result['status'])->not->toBe('PROJECTED')->and($result['xml'])->toBeNull()
        ->and($result['production_ready'])->toBeFalse();
})->with(['both', 'neither', 'origin-unknown', 'unknown-reason', 'integer-reason',
    'domestic', 'lowercase', 'country-space', 'leading-street', 'unsupported-street', 'long-nif',
    'blank-nif', 'missing-postal', 'long-postal', 'integer-postal', 'xml-control',
    'national-address', 'cpf-substitution', 'missing-evidence', 'unexpected-contact']);

it('preserves Unicode NIF and maximum source length without truncation or transliteration', function (): void {
    $party = dpsForeignParty75Fixture();
    $party['nif'] = '税'.str_repeat('N', 39);
    $result = (new DpsForeignPartyProjector)->project($party, 'toma', $this->foreignCodes75);
    expect($result['status'])->toBe('PROJECTED')->and($result['fields']['toma.NIF'])->toBe($party['nif']);
});

it('does not present a country pattern match as a qualified ISO country table entry', function (): void {
    $party = [...dpsForeignParty75Fixture(), 'country' => 'ZZ'];
    $result = (new DpsForeignPartyProjector)->project($party, 'toma', $this->foreignCodes75);
    expect($result['status'])->toBe('PROJECTED')->and($result['country_list_qualified'])->toBeFalse()
        ->and($result['native_complete_document_validation_performed'])->toBeFalse();
});

it('retains evidence linkage and binds the exact input without inventing authentication', function (): void {
    $party = dpsForeignParty75Fixture();
    $projector = new DpsForeignPartyProjector;
    $first = $projector->project($party, 'toma', $this->foreignCodes75);
    $party['identification_evidence_id'] = 'SYNTHETIC.OTHER.ID.75';
    $second = $projector->project($party, 'toma', $this->foreignCodes75);
    expect($second['xml'])->toBe($first['xml'])
        ->and($second['party_input_hash'])->not->toBe($first['party_input_hash'])
        ->and($second['evidence_links']['identification_evidence_id'])->toBe('SYNTHETIC.OTHER.ID.75')
        ->and($second['identity_authenticity_verified'])->toBeFalse();
});

it('respects distinct official name types instead of globally banning Unicode or normalizing names', function (): void {
    $party = [...dpsForeignParty75Fixture(), 'name' => ' 会社 '];
    $projector = new DpsForeignPartyProjector;
    $taker = $projector->project($party, 'toma', $this->foreignCodes75);
    expect($taker['status'])->toBe('PROJECTED')->and($taker['fields']['toma.xNome'])->toBe(' 会社 ')
        ->and($projector->project($party, 'IBSCBS.dest', $this->foreignCodes75)['status'])->toBe('INVALID');
    $party['name'] = str_repeat('A', 300);
    expect($projector->project($party, 'toma', $this->foreignCodes75)['status'])->toBe('PROJECTED')
        ->and($projector->project($party, 'IBSCBS.dest', $this->foreignCodes75)['status'])->toBe('INVALID');
});

it('refuses source substitution unsupported roles and missing bound facets', function (string $case): void {
    $codes = $this->foreignCodes75;
    $role = 'toma';
    if ($case === 'source') {
        $codes['dps_xsd']['source_sha256'] = str_repeat('0', 64);
    } elseif ($case === 'root') {
        $codes['dps_xsd']['root_schema'] = 'Other.xsd';
    } elseif ($case === 'role') {
        $role = 'dest';
    } else {
        unset($codes['dps_xsd_fields']['DPS.infDPS.toma.NIF']);
    }
    expect((new DpsForeignPartyProjector)->project(dpsForeignParty75Fixture(), $role, $codes)['status'])->toBe('ACTION_REQUIRED');
})->with(['source', 'root', 'role', 'facet']);
