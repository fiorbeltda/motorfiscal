<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DocumentIssIntegration134;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use App\Modules\Fiscal\Infrastructure\Scenario\ScenarioExecution;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require_once __DIR__.'/phase4b_iss134_fixture.php';
$factory = new FiscalEngineFactory;
$rtc = new RtcScenarioRunService($factory);
$directory = sys_get_temp_dir().'/iss134-native-'.bin2hex(random_bytes(6));
mkdir($directory);
try {
    foreach (['ordinary' => '000-no-adjustments-gross-before-all-discounts', 'discount' => '009-both-gross-before-all-discounts',
        'zero' => '021-zero-iss-price-gross-before-all-discounts', 'reverse' => '000-no-adjustments-gross-before-all-discounts',
        'fx' => '036-dated-variation-12', 'missing' => '000-no-adjustments-gross-before-all-discounts'] as $name => $seed) {
        $c = phase4bIss134Fixture($seed, $name === 'reverse');
        foreach (['rtc', 'integrated'] as $runner) {
            $priorTrace = null;
            $priorLegacy = null;
            foreach ($name === 'missing' ? ['134'] : ['131', '134'] as $version) {
                $knowledge = $version === '134' ? $c['knowledge']
                    : $factory->captureKnowledge(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.22-131-draft2.json');
                $source = $version === '134' ? $c['scenario'] : $c['component']['scenario'];
                if ($name === 'missing') {
                    unset($source['facts'][DocumentIssIntegration134::PROOFS]);
                }
                $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
                $result = $runner === 'rtc' ? $rtc->evaluate($knowledge, $loaded) : $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input);
                $snapshot = (new ScenarioExecution($loaded, $result, $knowledge))->snapshot();
                $assessment = $runner === 'rtc' ? $snapshot['result'] : array_column($snapshot['result']['trace'], 'payload', 'type')['CONSUMPTION_REFORM'];
                $normalized = array_values(array_filter($assessment['trace'], static fn (array $stage): bool => ! in_array($stage['stage'],
                    ['DOCUMENT_ISS_POLICY134', 'PARTIAL_EXPORT_DOCUMENT_ISS134', 'PARTIAL_EXPORT_DOCUMENT_PRICE_CONTEXT',
                        'PARTIAL_EXPORT_CONTRACT_PRICES', 'PARTIAL_EXPORT_ISS_PRICE_COMPOSITION'], true)));
                if ($priorTrace !== null && CanonicalJson::encode($priorTrace) !== CanonicalJson::encode($normalized)) {
                    throw new RuntimeException('Undeclared fiscal trace change:'.$name.'/'.$runner);
                }
                $priorTrace = $normalized;
                if ($runner === 'integrated') {
                    $legacy = array_column($snapshot['result']['trace'], 'payload', 'type')['ISS_CALCULATION'];
                    if ($priorLegacy !== null && $legacy !== $priorLegacy) {
                        throw new RuntimeException('Operation-level ISS changed.');
                    }
                    $priorLegacy = $legacy;
                }
                $expected = $name === 'missing' ? 'RTC_ISS134_TWO_TAX_CONTEXT_PROOFS_REQUIRED'
                    : ($version === '134' && $name === 'fx' ? 'ISS133_OWN_ISS_FX_CONTRACT_REQUIRED' : 'RTC_PARTIAL_EXPORT_DOCUMENT_OBLIGATION_NOT_PROVEN');
                if ($assessment['reason_codes'] !== [$expected]) {
                    throw new RuntimeException('Unexpected native134 result:'.json_encode($assessment['reason_codes']));
                }
                $restored = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($snapshot['input']);
                $matches = $runner === 'rtc' ? $rtc->replay($snapshot)
                    : $result->hash() === $factory->makeFromKnowledge($knowledge)->evaluate($restored->input)->hash();
                if (! $matches) {
                    throw new RuntimeException('Native134 replay failed.');
                }
                echo json_encode(['kind' => 'snapshot', 'case' => $name, 'runner' => $runner, 'version' => $version,
                    'replay_matches' => true, 'declared_document_iss_changes_only' => true, 'snapshot' => $snapshot],
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
                if ($runner !== 'integrated' || $version !== '134' || in_array($name, ['fx', 'missing'], true)) {
                    continue;
                }
                $options = phase4bIss134Options($snapshot);
                file_put_contents($directory.'/snapshot.json', json_encode($snapshot, JSON_THROW_ON_ERROR));
                file_put_contents($directory.'/options.json', json_encode($options, JSON_THROW_ON_ERROR));
                $reports = [];
                foreach (['first', 'second'] as $label) {
                    if (Artisan::call('fiscal:dps:project-document-values', ['snapshot' => $directory.'/snapshot.json',
                        'options' => $directory.'/options.json']) !== 0) {
                        throw new RuntimeException('Native134 fragments refused:'.Artisan::output());
                    }
                    $reports[$label] = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
                }
                if ($reports['first'] !== $reports['second']) {
                    throw new RuntimeException('Nondeterministic134 projection.');
                }
                foreach ($reports['first']['projection']['fragments'] as $index => $fragment) {
                    echo json_encode(['kind' => 'xml', 'case' => $name.'-'.$fragment['role'], 'options' => $options,
                        'report' => $reports['first'], 'validation' => $fragment['validation'],
                        'first_xml' => $fragment['xml'], 'second_xml' => $reports['second']['projection']['fragments'][$index]['xml']],
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
                }
            }
        }
    }
} finally {
    foreach (glob($directory.'/*') ?: [] as $path) {
        unlink($path);
    }
    rmdir($directory);
}
