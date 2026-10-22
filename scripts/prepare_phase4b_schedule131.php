<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DocumentSchedule131;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

require __DIR__.'/../vendor/autoload.php';
$base = __DIR__.'/../resources/fiscal/';
$read = static fn (string $path): array => json_decode((string) file_get_contents($base.$path), true, 512, JSON_THROW_ON_ERROR);
$write = static function (string $path, array $value) use ($base): void {
    $stream = fopen($base.$path, 'x');
    if ($stream === false) {
        throw new RuntimeException('Exclusive131 resource already exists.');
    }
    fwrite($stream, json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
    fflush($stream);
    fsync($stream);
    fclose($stream);
};
$bundle = $read('bundles/consumption-phase4b-2026.10.22-129-draft1.json');
$catalog = $read($bundle['resource_paths']['rtc_code_catalog']);
$controls = $read($bundle['production_controls']['path']);
if (hash_file('sha256', $base.'sources/official/'.DocumentSchedule131::policy()['source_file']) !== DocumentSchedule131::policy()['source_sha256']) {
    throw new RuntimeException('Preserved primary act bytes differ.');
}
$catalog['version'] = DocumentSchedule131::VERSION;
$catalog['document_schedule_policy131'] = DocumentSchedule131::policy();
$catalogPath = 'catalogs/rtc-codes-'.DocumentSchedule131::VERSION.'.json';
$write($catalogPath, $catalog);
$controls['version'] = DocumentSchedule131::VERSION;
$controlsPath = 'production/phase4b-controls-'.DocumentSchedule131::VERSION.'.json';
$write($controlsPath, $controls);
$bundle['predecessor_bundle_id'] = $bundle['bundle_id'];
$bundle['bundle_id'] = 'CONSUMPTION-PHASE4B-'.DocumentSchedule131::VERSION;
$bundle['version'] = DocumentSchedule131::VERSION;
$bundle['knowledge_available_at'] = DocumentSchedule131::AVAILABLE_AT;
$definitions = $read($bundle['resource_paths']['fact_definitions']);
$definitions[] = ['key' => 'rtc.recipient_regime', 'type' => 'ENUM', 'material' => true,
    'allowed_values' => ['REGULAR', 'SIMPLES_NACIONAL', 'MEI', 'NON_TAXPAYER']];
$questions = $read($bundle['resource_paths']['questions']);
$questions[] = ['fact_key' => 'rtc.recipient_regime', 'prompt' => 'Qual é o regime do destinatário brasileiro contribuinte da importação?',
    'rank' => 60, 'answer_type' => 'ENUM', 'options' => ['REGULAR', 'SIMPLES_NACIONAL', 'MEI', 'NON_TAXPAYER']];
$definitionPath = 'facts/consumption-phase4b-'.DocumentSchedule131::VERSION.'.json';
$questionPath = 'questions/consumption-integrated-'.DocumentSchedule131::VERSION.'.json';
$write($definitionPath, $definitions);
$write($questionPath, $questions);
$bundle['resource_paths']['fact_definitions'] = $definitionPath;
$bundle['resource_paths']['questions'] = $questionPath;
$bundle['content_hashes']['fact_definitions'] = CanonicalJson::hash($definitions);
$bundle['content_hashes']['questions'] = CanonicalJson::hash($questions);
$bundle['catalog_versions']['rtc_codes'] = DocumentSchedule131::VERSION;
$bundle['resource_paths']['rtc_code_catalog'] = $catalogPath;
$bundle['content_hashes']['rtc_code_catalog'] = CanonicalJson::hash($catalog);
$bundle['production_controls'] = ['path' => $controlsPath, 'sha256' => CanonicalJson::hash($controls)];
$bundle['notice'] = 'Primary-act131 schedule and exceptions;document fulfillment,municipal obligations and import body remain independent.';
$write('bundles/consumption-phase4b-'.DocumentSchedule131::VERSION.'.json', $bundle);
echo json_encode(['rows' => count(DocumentSchedule131::rows()), 'policy_sha256' => CanonicalJson::hash(DocumentSchedule131::policy())]).PHP_EOL;
