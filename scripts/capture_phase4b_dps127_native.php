<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DpsLayoutPolicy127;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioExecution;
use App\Modules\Fiscal\Infrastructure\Schema\DpsXmlSchemaValidator;
use App\Modules\Fiscal\NFSe\DpsRestrictedSchemaSelection127;
use App\Modules\Fiscal\NFSe\DpsXmlProjector127;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$factory = new FiscalEngineFactory;
$rtc = new RtcScenarioRunService($factory);
$knowledge = [];
foreach (['126', '127'] as $version) {
    $knowledge[$version] = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.22-'.$version.'-draft1.json');
}
foreach (['federal', 'education', 'presential'] as $name) {
    $path = resource_path('fiscal/scenarios/dps-local-2026.10.14-74-draft1/'.$name);
    $source = json_decode((string) file_get_contents($path.'-scenario.json'), true, 512, JSON_THROW_ON_ERROR);
    $identity = json_decode((string) file_get_contents($path.'-identity.json'), true, 512, JSON_THROW_ON_ERROR);
    $identity['issued_at'] = '2026-10-22T11:19:00+00:00';
    $identity['identity_evidence_id'] = 'SYNTHETIC-DPS127-'.$name;
    foreach (['rtc', 'integrated'] as $runner) {
        $previous = null;
        foreach (['126', '127'] as $version) {
            $known = $knowledge[$version];
            $input = $source;
            $input['scenario_id'] = 'DPS127-SYNTHETIC-'.$name;
            $input['rule_bundle_id'] = $known['rule_bundle_id'];
            $input['catalog_versions'] = $known['catalog_versions'];
            $input['knowledge_at'] = '2026-10-22T11:20:00+00:00';
            $loaded = $factory->scenarioLoaderFromKnowledge($known)->fromArray($input);
            $result = $runner === 'rtc' ? $rtc->evaluate($known, $loaded)
                : $factory->makeFromKnowledge($known)->evaluate($loaded->input);
            $snapshot = (new ScenarioExecution($loaded, $result, $known))->snapshot();
            $saved = $snapshot['result'];
            if ($saved['status'] !== ($runner === 'rtc' ? 'VALID' : 'VALID_WITH_WARNINGS')) {
                throw new RuntimeException('Unexpected native status: '.$name.' '.$version);
            }
            $assessment = $runner === 'rtc' ? $saved : array_column($saved['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
            $compare = ['status' => $assessment['status'], 'decision' => $assessment['decision'], 'reason_codes' => $assessment['reason_codes'], 'missing_facts' => $assessment['missing_facts']];
            if ($version === '126') {
                if ($compare['decision']['document']['schema_version'] !== 'NT004-RTC-1.01.03'
                    || $compare['decision']['document']['schema_source_hash'] !== 'c32c30a2aac3aaf8eb71a8baf512954aac70867d883343d202d1c1e9b4453651') {
                    throw new RuntimeException('Unexpected prior schema metadata.');
                }
                $compare['decision']['document']['schema_version'] = 'RESTRICTED-LOCAL-OBSERVED-2026.10.22-127';
                $compare['decision']['document']['schema_source_hash'] = DpsLayoutPolicy127::SOURCE_SHA256;
                if ($compare['decision']['document']['issuer_field_validation']['xsd_source_hash'] !== '6c7e0510d3ecff4454f291f4e10b742d27a4818f23aab181494f96d0ea79f3dc') {
                    throw new RuntimeException('Unexpected prior validation source hash.');
                }
                $compare['decision']['document']['issuer_field_validation']['xsd_source_hash'] = DpsLayoutPolicy127::SOURCE_SHA256;
                $compare['decision']['document']['issuer_fields'] = (new DpsLayoutPolicy127)->mapIssuerFields($compare['decision']['document']['issuer_fields']);
                $compare['decision']['catalog_versions']['codes'] = DpsLayoutPolicy127::VERSION;
            }
            $same = $previous === null || CanonicalJson::hash($compare) === CanonicalJson::hash($previous);
            if (! $same) {
                $differences = [];
                $walk = function (mixed $a, mixed $b, string $path) use (&$walk, &$differences): void {
                    if (is_array($a) && is_array($b)) {
                        foreach (array_unique([...array_keys($a), ...array_keys($b)]) as $key) {
                            $walk($a[$key] ?? null, $b[$key] ?? null, $path.'.'.$key);
                        }
                    } elseif ($a !== $b) {
                        $differences[$path] = [$a, $b];
                    }
                };
                $walk($previous, $compare, 'assessment');
                throw new RuntimeException('Undeclared differences: '.json_encode($differences));
            }
            $previous = $compare;
            $matches = $runner === 'rtc' ? $rtc->replay($snapshot)
                : hash_equals($result->hash(), $factory->makeFromKnowledge($known)->evaluate($loaded->input)->hash());
            if (! $matches) {
                throw new RuntimeException('Native replay mismatch.');
            }
            echo json_encode(['kind' => 'snapshot', 'case' => $name, 'runner' => $runner, 'version' => $version,
                'replay_matches' => $matches, 'declared_document_changes_only' => $same, 'snapshot' => $snapshot], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION).PHP_EOL;
            if ($runner !== 'integrated' || $version !== '127') {
                continue;
            }
            $codes = $known['documents']['rtc_code_catalog'];
            $selection = (new DpsRestrictedSchemaSelection127)->qualify($identity, $codes, $input['knowledge_at']);
            $first = (new DpsXmlProjector127)->project($saved['decision'], $identity);
            $second = (new DpsXmlProjector127)->project($saved['decision'], $identity);
            if ($selection['status'] !== 'VALID' || $first['status'] !== 'PROJECTED'
                || ! is_string($first['xml']) || $first['xml'] !== $second['xml']) {
                throw new RuntimeException('Deterministic projection failed.');
            }
            $validation = (new DpsXmlSchemaValidator)->validate($first['xml'],
                resource_path('fiscal/sources/official/'.$codes['dps_xsd']['source_file']), DpsLayoutPolicy127::SOURCE_SHA256, 'DPS_v1.01.xsd');
            if ($validation['status'] !== 'VALID') {
                throw new RuntimeException('Observed XSD refused native XML: '.json_encode($validation));
            }
            echo json_encode(['kind' => 'xml', 'case' => $name, 'identity' => $identity,
                'selection' => $selection, 'validation' => $validation, 'first_xml' => $first['xml'], 'second_xml' => $second['xml'],
                'decision_hash' => $saved['decision_hash'], 'production_ready' => false], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).PHP_EOL;
        }
    }
}
