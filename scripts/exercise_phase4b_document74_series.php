<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use App\Modules\Fiscal\Infrastructure\Schema\DpsXmlSchemaValidator;

$root = dirname(__DIR__);
$evidence = $root.'/storage/app/phase4b-document74-primary-research-20261014';
$xml = (string) file_get_contents($root.'/tests/Fixtures/dps-production-schema-valid.xml');
$xml = str_replace('DPS000000000000000000000000000000000000000000',
    'DPS35503082'.'11111111000191'.str_repeat('0', 19).'1', $xml);
$validator = new DpsXmlSchemaValidator;
$reports = [];
foreach (['1', '00001', '89999', '90000', '99999', '^1$', '123456', 'ABC'] as $series) {
    $candidate = str_replace('<serie>^1$</serie>', '<serie>'.$series.'</serie>', $xml);
    $reports[$series] = $validator->validate($candidate, $evidence.'/restricted-xsd-0.zip',
        '6c7e0510d3ecff4454f291f4e10b742d27a4818f23aab181494f96d0ea79f3dc', 'DPS_v1.01.xsd');
}
$target = $evidence.'/restricted-series-native-validation-v2.json';
$stream = fopen($target, 'x');
if ($stream === false) {
    throw new RuntimeException('Exclusive evidence target unavailable.');
}
try {
    fwrite($stream, json_encode(['status' => 'LOCAL_LIBXML_SERIES_OBSERVATION_NO_LAYOUT_ACTIVATION',
        'cases' => $reports, 'remote_deployment_verified' => false, 'production_ready' => false],
        JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
} finally {
    fclose($stream);
}
foreach ($reports as $series => $report) {
    echo $series.': '.$report['status'].' '.$report['reason'].PHP_EOL;
}
