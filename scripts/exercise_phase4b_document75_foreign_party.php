<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use App\Modules\Fiscal\Infrastructure\Schema\DpsXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DpsForeignPartyProjector;
use App\Modules\Fiscal\NFSe\DpsRestrictedSchemaSelection;

$root = dirname(__DIR__);
$evidence = $root.'/storage/app/phase4b-document75-foreign-party-native-v3-20261015';
if (! mkdir($evidence, 0700)) {
    throw new RuntimeException('Exclusive foreign party evidence directory unavailable.');
}
$read = static fn (string $path): array => json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$save = static function (string $path, string $data): void {
    $stream = fopen($path, 'x');
    if ($stream === false) {
        throw new RuntimeException('Exclusive native evidence file unavailable.');
    }
    try {
        if (fwrite($stream, $data) !== strlen($data) || ! fflush($stream)) {
            throw new RuntimeException('Incomplete native evidence write.');
        }
    } finally {
        fclose($stream);
    }
};
$corpus = $read($root.'/storage/app/phase4b-schema74-cli-corpus-v2-20261014/cli-corpus-validation.json');
$baseRecord = $corpus['cases'][0]['xml'][0];
$basePath = $root.'/'.$baseRecord['xml'];
if (hash_file('sha256', $basePath) !== $baseRecord['xml_sha256']) {
    throw new RuntimeException('Accepted structural base changed.');
}
$base = (string) file_get_contents($basePath);
$codes = $read($root.'/resources/fiscal/catalogs/rtc-codes-2026.10.14-74-draft1.json');
$schema = $root.'/resources/fiscal/sources/official/'.$codes['dps_xsd']['source_file'];
$party = ['country' => 'US', 'name' => 'Foreign & Company', 'nif' => '税-NIF-75',
    'address' => ['postal_code' => '10001', 'city' => 'New York', 'province' => 'NY',
        'street' => 'Main & Second', 'number' => '10', 'complement' => 'Floor 2', 'district' => 'Manhattan'],
    'party_evidence_id' => 'SYNTHETIC.PARTY.75', 'identification_evidence_id' => 'SYNTHETIC.ID.75',
    'address_evidence_id' => 'SYNTHETIC.ADDRESS.75'];
$validator = new DpsXmlSchemaValidator;
$projector = new DpsForeignPartyProjector;
$ns = 'http://www.sped.fazenda.gov.br/nfse';
$records = [];
foreach (['toma', 'IBSCBS.dest'] as $role) {
    foreach (['NIF', '1', '2', ...($role === 'toma' ? ['unicode-name', 'long-name'] : [])] as $choice) {
        $input = $party;
        if (in_array($choice, ['1', '2'], true)) {
            unset($input['nif']);
            $input['no_nif_reason'] = $choice;
        } elseif ($choice === 'unicode-name') {
            $input['name'] = ' 会社 ';
        } elseif ($choice === 'long-name') {
            $input['name'] = str_repeat('A', 300);
        }
        $fragment = $projector->project($input, $role, $codes);
        if ($fragment['status'] !== 'PROJECTED' || $fragment !== $projector->project($input, $role, $codes)) {
            throw new RuntimeException('Fragment projection failed or is nondeterministic.');
        }
        $document = new DOMDocument;
        $fragmentDocument = new DOMDocument;
        if (! $document->loadXML($base, LIBXML_NONET) || ! $fragmentDocument->loadXML($fragment['xml'], LIBXML_NONET)) {
            throw new RuntimeException('Native XML unavailable.');
        }
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('n', $ns);
        $inserted = $document->importNode($fragmentDocument->documentElement, true);
        if ($role === 'toma') {
            $old = $xpath->query('/n:DPS/n:infDPS/n:toma')->item(0);
            $old->parentNode->replaceChild($inserted, $old);
        } else {
            $rtc = $xpath->query('/n:DPS/n:infDPS/n:IBSCBS')->item(0);
            $xpath->query('/n:DPS/n:infDPS/n:IBSCBS/n:indDest')->item(0)->textContent = '1';
            $values = $xpath->query('/n:DPS/n:infDPS/n:IBSCBS/n:valores')->item(0);
            $rtc->insertBefore($inserted, $values);
        }
        $xml = (string) $document->saveXML();
        $validation = $validator->validate($xml, $schema, DpsRestrictedSchemaSelection::ARCHIVE_SHA256, 'DPS_v1.01.xsd');
        if ($validation['status'] !== 'VALID') {
            throw new RuntimeException('Unexpected native foreign identity status: '.json_encode($validation));
        }
        $label = str_replace('.', '-', $role).'-'.$choice;
        $save($evidence.'/'.$label.'.xml', $xml);
        $records[] = ['label' => $label, 'role' => $role, 'scope' => 'SYNTHETIC_STRUCTURAL_ENVELOPE_NOT_FISCAL_DECISION',
            'party_input' => $input, 'party_input_hash' => $fragment['party_input_hash'], 'evidence_links' => $fragment['evidence_links'],
            'fragment_sha256' => $fragment['xml_sha256'], 'xml_sha256' => hash('sha256', $xml), 'validation' => $validation];
        if ($choice !== 'NIF') {
            continue;
        }
        foreach (['duplicate-id', 'missing-id', 'postal-overflow', ...($role === 'IBSCBS.dest' ? ['unsupported-name'] : [])] as $mutation) {
            $mutated = new DOMDocument;
            $mutated->loadXML($xml, LIBXML_NONET);
            $query = new DOMXPath($mutated);
            $query->registerNamespace('n', $ns);
            $path = $role === 'toma' ? '/n:DPS/n:infDPS/n:toma' : '/n:DPS/n:infDPS/n:IBSCBS/n:dest';
            $target = $query->query($path)->item(0);
            if ($mutation === 'duplicate-id') {
                $cpf = $mutated->createElementNS($ns, 'CPF', '12345678901');
                $target->insertBefore($cpf, $target->firstChild);
            } elseif ($mutation === 'missing-id') {
                $target->removeChild($query->query($path.'/n:NIF')->item(0));
            } elseif ($mutation === 'postal-overflow') {
                $query->query($path.'/n:end/n:endExt/n:cEndPost')->item(0)->textContent = str_repeat('1', 12);
            } else {
                $query->query($path.'/n:xNome')->item(0)->textContent = '会社';
            }
            $badXml = (string) $mutated->saveXML();
            $bad = $validator->validate($badXml, $schema, DpsRestrictedSchemaSelection::ARCHIVE_SHA256, 'DPS_v1.01.xsd');
            if ($bad['status'] !== 'INVALID') {
                throw new RuntimeException('Native schema did not reject corruption: '.$mutation);
            }
            $badLabel = $label.'-'.$mutation;
            $save($evidence.'/'.$badLabel.'.xml', $badXml);
            $records[] = ['label' => $badLabel, 'role' => $role,
                'scope' => 'DELIBERATELY_CORRUPTED_STRUCTURAL_ENVELOPE', 'xml_sha256' => hash('sha256', $badXml), 'validation' => $bad];
        }
    }
}
if (hash_file('sha256', $basePath) !== $baseRecord['xml_sha256']) {
    throw new RuntimeException('Accepted structural base was changed.');
}
$valid = count(array_filter($records, static fn (array $row): bool => $row['validation']['status'] === 'VALID'));
$invalid = count($records) - $valid;
$report = ['status' => 'FOREIGN_IDENTITY_FRAGMENTS_AND_OFFICIAL_SCHEMA_CHOICES_PROBED',
    'base_xml_path' => $baseRecord['xml'], 'base_xml_sha256' => $baseRecord['xml_sha256'],
    'projector_sha256' => hash_file('sha256', $root.'/app/Modules/Fiscal/NFSe/DpsForeignPartyProjector.php'),
    'exercise_sha256' => hash_file('sha256', __FILE__),
    'schema_sha256' => DpsRestrictedSchemaSelection::ARCHIVE_SHA256, 'cases' => $records,
    'valid_structural_envelopes' => $valid, 'invalid_structural_envelopes' => $invalid,
    'integrated_fiscal_decision_qualified' => false, 'foreign_identity_authenticity_verified' => false,
    'country_table_validity_qualified' => false, 'document_obligation_complete' => false,
    'nt009_migration_complete' => false, 'production_ready' => false];
$save($evidence.'/foreign-party-native-validation.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo 'PASS: '.$valid.' structural foreign envelopes;'.$invalid.' deliberate corruptions rejected;not a fiscal document pair'.PHP_EOL;
