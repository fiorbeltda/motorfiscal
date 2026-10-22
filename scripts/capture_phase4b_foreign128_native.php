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
foreach (['127', '128'] as $version) {
    $knowledge[$version] = $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.22-'.$version.'-draft1.json');
}
$cases = [];
foreach (['nif', 'no-nif-1', 'no-nif-2'] as $name) {
    $cases['foreign-'.$name] = 'scenarios/dps-foreign-acquirer-2026.10.15-76-draft1/'.$name;
}
foreach (['federal', 'education', 'presential'] as $name) {
    $cases[$name] = 'scenarios/dps-local-2026.10.14-74-draft1/'.$name;
}
foreach ($cases as $name => $relative) {
    $path = resource_path('fiscal/'.$relative);
    $source = json_decode((string) file_get_contents($path.'-scenario.json'), true, 512, JSON_THROW_ON_ERROR);
    $identity = json_decode((string) file_get_contents($path.'-identity.json'), true, 512, JSON_THROW_ON_ERROR);
    $identity['issued_at'] = '2026-10-22T11:37:00+00:00';
    $identity['identity_evidence_id'] = 'SYNTHETIC-DPS128-'.$name;
    foreach (['rtc', 'integrated'] as $runner) {
        $previous = null;
        $priorIntegrated = null;
        foreach (['127', '128'] as $version) {
            $known = $knowledge[$version];
            $input = $source;
            $input['scenario_id'] = 'DPS128-SYNTHETIC-'.$name;
            $input['rule_bundle_id'] = $known['rule_bundle_id'];
            $input['catalog_versions'] = $known['catalog_versions'];
            $input['knowledge_at'] = '2026-10-22T11:38:00+00:00';
            $loaded = $factory->scenarioLoaderFromKnowledge($known)->fromArray($input);
            $result = $runner === 'rtc' ? $rtc->evaluate($known, $loaded) : $factory->makeFromKnowledge($known)->evaluate($loaded->input);
            $snapshot = (new ScenarioExecution($loaded, $result, $known))->snapshot();
            $saved = $snapshot['result'];
            if ($saved['status'] !== ($runner === 'rtc' ? 'VALID' : 'VALID_WITH_WARNINGS')) {
                throw new RuntimeException('Unexpected result: '.$name.' '.$version);
            }
            $assessment = $runner === 'rtc' ? $saved : array_column($saved['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
            $compare = ['status' => $assessment['status'], 'decision' => $assessment['decision'], 'reason_codes' => $assessment['reason_codes'], 'missing_facts' => $assessment['missing_facts']];
            if ($version === '127') {
                $compare['decision']['catalog_versions']['codes'] = '2026.10.22-128-draft1';
            }
            $same = $previous === null || CanonicalJson::hash($compare) === CanonicalJson::hash($previous);
            if (! $same) {
                throw new RuntimeException('RTC changed beyond the declared catalog version.');
            }
            $previous = $compare;
            if ($runner === 'integrated') {
                $legacy = ['iss' => $saved['decision']['iss'], 'federal' => $saved['decision']['federal_withholdings']];
                if ($priorIntegrated !== null && CanonicalJson::hash($legacy) !== CanonicalJson::hash($priorIntegrated)) {
                    throw new RuntimeException('Legacy tax decision changed.');
                }
                $priorIntegrated = $legacy;
            }
            $matches = $runner === 'rtc' ? $rtc->replay($snapshot) : hash_equals($result->hash(), $factory->makeFromKnowledge($known)->evaluate($loaded->input)->hash());
            if (! $matches) {
                throw new RuntimeException('Native replay mismatch.');
            }
            echo json_encode(['kind' => 'snapshot', 'case' => $name, 'runner' => $runner, 'version' => $version,
                'original_scenario' => $relative.'-scenario.json', 'replay_matches' => $matches,
                'declared_document_changes_only' => $same, 'snapshot' => $snapshot], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION).PHP_EOL;
            if ($runner !== 'integrated' || $version !== '128') {
                continue;
            }
            $codes = $known['documents']['rtc_code_catalog'];
            $selection = (new DpsRestrictedSchemaSelection127)->qualify($identity, $codes, $input['knowledge_at']);
            $first = (new DpsXmlProjector127)->project($saved['decision'], $identity, $codes);
            $second = (new DpsXmlProjector127)->project($saved['decision'], $identity, $codes);
            if ($selection['status'] !== 'VALID' || $first['status'] !== 'PROJECTED' || ! is_string($first['xml']) || $first['xml'] !== $second['xml']) {
                throw new RuntimeException('Projection failed.');
            }
            $validation = (new DpsXmlSchemaValidator)->validate($first['xml'], resource_path('fiscal/sources/official/'.$codes['dps_xsd']['source_file']), DpsLayoutPolicy127::SOURCE_SHA256, 'DPS_v1.01.xsd');
            if ($validation['status'] !== 'VALID') {
                throw new RuntimeException('XSD validation failed.');
            }
            echo json_encode(['kind' => 'xml', 'case' => $name, 'identity' => $identity, 'selection' => $selection,
                'validation' => $validation, 'foreign_binding' => $first['foreign_acquirer_binding'] ?? null,
                'first_xml' => $first['xml'], 'second_xml' => $second['xml'], 'decision_hash' => $saved['decision_hash'], 'production_ready' => false], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).PHP_EOL;
        }
    }
}
