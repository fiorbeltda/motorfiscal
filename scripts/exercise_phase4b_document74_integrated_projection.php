<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Schema\DpsXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DpsXmlProjector;

$root = dirname(__DIR__);
$evidence = $root.'/storage/app/phase4b-document74-integrated-projection-20261014';
if (! mkdir($evidence, 0700)) {
    throw new RuntimeException('Exclusive evidence directory unavailable.');
}
$read = static function (string $path): array {
    $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($value)) {
        throw new RuntimeException('Expected JSON array.');
    }

    return $value;
};
$save = static function (string $path, string $value): void {
    $stream = fopen($path, 'x');
    if ($stream === false) {
        throw new RuntimeException('Exclusive evidence file unavailable.');
    }
    try {
        if (fwrite($stream, $value) !== strlen($value)) {
            throw new RuntimeException('Incomplete evidence write.');
        }
    } finally {
        fclose($stream);
    }
};
$schema = $root.'/storage/app/phase4b-document74-primary-research-20261014/restricted-xsd-0.zip';
$schemaHash = '6c7e0510d3ecff4454f291f4e10b742d27a4818f23aab181494f96d0ea79f3dc';
$cases = [
    'integrated-federal' => ['storage/app/phase4b-dps-integrated-20261001-2-snapshot.json',
        'resources/fiscal/scenarios/dps-local-2026.10.01-2/federal-identity.json'],
    'education' => ['storage/app/phase4b-education-20261001-5-snapshot.json',
        'resources/fiscal/scenarios/dps-local-2026.10.01-5/presential-identity.json'],
    'integrated-presential' => ['storage/app/phase4b-integrated-education-presential-20261001-6-snapshot.json',
        'resources/fiscal/scenarios/dps-local-2026.10.01-6/presential-identity.json'],
];
$factory = new FiscalEngineFactory;
$validator = new DpsXmlSchemaValidator;
$projector = new DpsXmlProjector;
$records = [];
foreach ($cases as $name => [$snapshotPath, $identityPath]) {
    $snapshot = $read($root.'/'.$snapshotPath);
    $originalSnapshotHash = hash_file('sha256', $root.'/'.$snapshotPath);
    $scenario = $factory->scenarioLoaderFromKnowledge($snapshot['knowledge'])->fromArray($snapshot['input']);
    $replayed = $factory->makeFromKnowledge($snapshot['knowledge'])->evaluate($scenario->input)->toArray();
    if (CanonicalJson::encode($replayed) !== CanonicalJson::encode($snapshot['result'])) {
        throw new RuntimeException('Historical replay mismatch: '.$name);
    }
    $identity = $read($root.'/'.$identityPath);
    $results = [];
    foreach (['1', '00001', '89999', '90000'] as $series) {
        $input = [...$identity, 'series' => $series];
        $projection = $projector->project($snapshot['result']['decision'], $input);
        if ($projection['status'] !== 'PROJECTED' || ! is_string($projection['xml'])) {
            throw new RuntimeException('Projection failed: '.$name.' '.$projection['reason']);
        }
        $second = $projector->project($snapshot['result']['decision'], $input);
        if ($second !== $projection) {
            throw new RuntimeException('Nondeterministic projection.');
        }
        $validation = $validator->validate($projection['xml'], $schema, $schemaHash, 'DPS_v1.01.xsd');
        $expected = $series === '90000' ? 'INVALID' : 'VALID';
        if ($validation['status'] !== $expected) {
            throw new RuntimeException('Unexpected schema status: '.$name.' '.$series.' '.json_encode($validation));
        }
        $xmlPath = $name.'-'.$series.'.xml';
        $save($evidence.'/'.$xmlPath, $projection['xml']);
        $results[] = ['series' => $series, 'identity_hash' => CanonicalJson::hash($input),
            'xml_file' => $xmlPath, 'xml_sha256' => hash('sha256', $projection['xml']),
            'deterministic_projection' => true, 'schema_validation' => $validation];
    }
    if (hash_file('sha256', $root.'/'.$snapshotPath) !== $originalSnapshotHash) {
        throw new RuntimeException('Historical snapshot changed.');
    }
    $records[] = ['name' => $name, 'snapshot_path' => $snapshotPath, 'snapshot_sha256' => $originalSnapshotHash,
        'identity_path' => $identityPath, 'identity_sha256' => hash_file('sha256', $root.'/'.$identityPath),
        'bundle_id' => $snapshot['input']['rule_bundle_id'], 'historical_exact_replay' => true,
        'cases' => $results];
    echo 'PASS: '.$name.' historical exact replay;three numeric series valid,90000 rejected'.PHP_EOL;
}
$report = ['status' => 'HISTORICAL_INTEGRATED_DPS_REPLAY_AND_RESTRICTED_XSD_PROBE_VERIFIED_NOT_ACTIVATED',
    'cases' => $records, 'schema_sha256' => $schemaHash,
    'projector_sha256' => hash_file('sha256', $root.'/app/Modules/Fiscal/NFSe/DpsXmlProjector.php'),
    'validator_sha256' => hash_file('sha256', $root.'/app/Modules/Fiscal/Infrastructure/Schema/DpsXmlSchemaValidator.php'),
    'runtime_changed' => false, 'new_bundle_activated' => false, 'nt009_migration_complete' => false,
    'remote_deployment_verified' => false, 'production_ready' => false];
$save($evidence.'/integrated-projection-validation.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
