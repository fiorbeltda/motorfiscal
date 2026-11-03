<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;

/** @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function collection148Scenario(array $overrides = []): array
{
    $root = dirname(__DIR__, 2).'/resources/fiscal/';
    $source = json_decode((string) file_get_contents($root.'scenarios/consumption-phase4b/regular-saas-2026.json'), true, 512, JSON_THROW_ON_ERROR);
    $manifest = json_decode((string) file_get_contents($root.'bundles/consumption-phase4b-2026.09.28-3.json'), true, 512, JSON_THROW_ON_ERROR);
    $source['scenario_id'] = 'FIXTURE.COLLECTION148';
    $source['knowledge_at'] = '2026-09-28T11:33:00-03:00';
    $source['rule_bundle_id'] = $manifest['bundle_id'];
    $source['catalog_versions'] = $manifest['catalog_versions'];
    $source['facts'] = array_replace($source['facts'], [
        'rtc.collection_method' => 'ACQUIRER', 'rtc.collection_legal_basis' => 'LC214 art. 36',
        'rtc.acquirer_regime' => 'REGULAR', 'rtc.payment_instrument_split_capable' => false,
        'rtc.payment_instrument_evidence_id' => 'FIXTURE.INSTRUMENT', 'rtc.payment_transaction_id' => '0001',
        'rtc.accessory_obligations_fulfilled' => false,
    ], $overrides);
    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($source['facts'][$key]);
        }
    }

    return $source;
}

/** @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function collection148Run(array $overrides = []): array
{
    $factory = new FiscalEngineFactory;
    $knowledge = $factory->captureKnowledge(dirname(__DIR__, 2).'/resources/fiscal', 'bundles/consumption-phase4b-2026.09.28-3.json');
    $source = collection148Scenario($overrides);
    $loaded = $factory->scenarioLoaderFromKnowledge($knowledge)->fromArray($source);
    $rtc = (new RtcScenarioRunService($factory))->evaluate($knowledge, $loaded)->toArray();
    $full = $factory->makeFromKnowledge($knowledge)->evaluate($loaded->input)->toArray();

    return ['rtc' => $rtc, 'full' => $full, 'snapshot' => ['input' => $source, 'knowledge' => $knowledge, 'result' => $rtc]];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    if (($argv[1] ?? null) === '--write-scenario') {
        $directory = dirname(__DIR__, 2).'/storage/app/agent-a-A148';
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($directory.'/valid.json', json_encode(collection148Scenario(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        file_put_contents($directory.'/blank-transaction.json', json_encode(collection148Scenario(['rtc.payment_transaction_id' => ' ']), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        echo $directory.PHP_EOL;

        exit(0);
    }
    $rows = [];
    foreach (['rtc.payment_instrument_evidence_id', 'rtc.payment_transaction_id', 'rtc.supplier_id', 'rtc.acquirer_id'] as $key) {
        $result = collection148Run([$key => ' ']);
        $rows[] = ['field' => $key, 'value' => ' ', 'rtc' => $result['rtc'], 'full' => $result['full']];
    }
    echo json_encode(['observations' => $rows], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
}
