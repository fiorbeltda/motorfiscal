<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use App\Modules\Fiscal\Infrastructure\Schema\DpsXmlSchemaValidator;

$root = dirname(__DIR__);
$evidence = $root.'/storage/app/phase4b-document74-primary-research-20261014';
$base = (string) file_get_contents($root.'/storage/app/phase4b-document74-integrated-projection-20261014/integrated-federal-1.xml');
$validator = new DpsXmlSchemaValidator;
$cases = [];
foreach (['old-transborder' => ['1', false, 'VALID'],
    'old-mode-four' => ['4', false, 'VALID'],
    'new-mixed-consumption' => ['5', false, 'INVALID'],
    'new-invoice-element' => ['1', true, 'INVALID']] as $name => [$mode, $invoice, $expected]) {
    $document = new DOMDocument;
    if (! $document->loadXML($base, LIBXML_NONET)) {
        throw new RuntimeException('Base XML unavailable.');
    }
    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('n', 'http://www.sped.fazenda.gov.br/nfse');
    $service = $xpath->query('/n:DPS/n:infDPS/n:serv')?->item(0);
    if (! $service instanceof DOMElement) {
        throw new RuntimeException('Service node unavailable.');
    }
    $group = $document->createElementNS('http://www.sped.fazenda.gov.br/nfse', 'comExt');
    $service->appendChild($group);
    $fields = ['mdPrestacao' => $mode, 'vincPrest' => '0', 'tpMoeda' => '220',
        'vServMoeda' => '100.00', 'mecAFComexP' => '01', 'mecAFComexT' => '01',
        'movTempBens' => '1', 'mdic' => '0'];
    if ($invoice) {
        $fields['nFatura'] = '1234';
    }
    foreach ($fields as $tag => $value) {
        $node = $document->createElementNS('http://www.sped.fazenda.gov.br/nfse', $tag);
        $node->appendChild($document->createTextNode($value));
        $group->appendChild($node);
    }
    $xml = $document->saveXML();
    if (! is_string($xml)) {
        throw new RuntimeException('XML serialization failed.');
    }
    $report = $validator->validate($xml, $evidence.'/restricted-xsd-0.zip',
        '6c7e0510d3ecff4454f291f4e10b742d27a4818f23aab181494f96d0ea79f3dc', 'DPS_v1.01.xsd');
    if ($report['status'] !== $expected) {
        throw new RuntimeException('Unexpected status: '.$name.' '.json_encode($report));
    }
    $cases[$name] = ['scope' => 'SYNTHETIC_STRUCTURE_ONLY_NO_FISCAL_OPERATION_QUALIFICATION',
        'validation' => $report, 'xml_sha256' => hash('sha256', $xml)];
    echo $name.': '.$report['status'].PHP_EOL;
}
$stream = fopen($evidence.'/comext-native-validation.json', 'x');
if ($stream === false) {
    throw new RuntimeException('Exclusive target unavailable.');
}
try {
    $json = json_encode(['status' => 'PRESERVED_XSD_REJECTS_NEW_MIXED_MODE_AND_INVOICE_ELEMENT',
        'cases' => $cases, 'nt009_migration_complete' => false, 'production_ready' => false],
        JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    if (fwrite($stream, $json) !== strlen($json)) {
        throw new RuntimeException('Incomplete evidence write.');
    }
} finally {
    fclose($stream);
}
