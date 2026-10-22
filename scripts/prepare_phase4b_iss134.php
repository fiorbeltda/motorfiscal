<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\DocumentIssIntegration134;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

require __DIR__.'/../vendor/autoload.php';
$root = __DIR__.'/../resources/fiscal/';
$read = static fn (string $path): array => json_decode((string) file_get_contents($root.$path), true, 512, JSON_THROW_ON_ERROR);
$write = static function (string $path, array $value) use ($root): void {
    $stream = fopen($root.$path, 'x');
    if ($stream === false) {
        throw new RuntimeException('Exclusive134 resource already exists.');
    }
    $raw = json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
    if (fwrite($stream, $raw) !== strlen($raw) || ! fflush($stream) || ! fsync($stream)) {
        throw new RuntimeException('Unable to preserve complete134 resource.');
    }
    fclose($stream);
};
$sources = json_decode((string) stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
if (! is_array($sources) || count($sources['records'] ?? []) !== 5) {
    throw new RuntimeException('Five preserved source capture records required on stdin.');
}
foreach ($sources['records'] as $source) {
    if (hash_file('sha256', $root.'sources/official/iss133-'.$source['file']) !== $source['sha256']) {
        throw new RuntimeException('Municipal134 source preflight differs from preserved observation.');
    }
}
$bundle = $read('bundles/consumption-phase4b-2026.10.22-131-draft2.json');
$catalog = $read($bundle['resource_paths']['rtc_code_catalog']);
$definitions = $read($bundle['resource_paths']['fact_definitions']);
$questions = $read($bundle['resource_paths']['questions']);
$controls = $read($bundle['production_controls']['path']);
$version = DocumentIssIntegration134::VERSION;
$catalog['version'] = $version;
$catalog['document_iss_policy134'] = DocumentIssIntegration134::policy();
$definitions[] = ['key' => DocumentIssIntegration134::PROOFS, 'type' => 'ARRAY', 'material' => true];
$questions[] = ['fact_key' => DocumentIssIntegration134::PROOFS,
    'prompt' => 'Quais são as duas provas de contexto tributário ISS por documento,com classificação municipal granular?',
    'rank' => 60, 'answer_type' => 'ARRAY'];
$controls['version'] = $version;
$bundle['predecessor_bundle_id'] = $bundle['bundle_id'];
$bundle['bundle_id'] = 'CONSUMPTION-PHASE4B-'.$version;
$bundle['version'] = $version;
$bundle['knowledge_available_at'] = DocumentIssIntegration134::AVAILABLE_AT;
foreach (['rtc_code_catalog' => ['catalogs/rtc-codes-'.$version.'.json', $catalog],
    'fact_definitions' => ['facts/consumption-phase4b-'.$version.'.json', $definitions],
    'questions' => ['questions/consumption-integrated-'.$version.'.json', $questions]] as $key => [$path, $value]) {
    $write($path, $value);
    $bundle['resource_paths'][$key] = $path;
    $bundle['content_hashes'][$key] = CanonicalJson::hash($value);
}
$bundle['catalog_versions']['rtc_codes'] = $version;
$controlsPath = 'production/phase4b-controls-'.$version.'.json';
$write($controlsPath, $controls);
$bundle['production_controls'] = ['path' => $controlsPath, 'sha256' => CanonicalJson::hash($controls)];
foreach ($sources['records'] as $source) {
    $file = 'iss133-'.$source['file'];
    if (hash_file('sha256', $root.'sources/official/'.$file) !== $source['sha256']) {
        throw new RuntimeException('Municipal134 source differs from preserved observation.');
    }
    $bundle['official_sources'][] = ['id' => 'ISS134_'.strtoupper(str_replace(['-', '.'], '_', $source['file'])),
        'file' => $file, 'version' => 'OBSERVED20261022', 'url' => $source['url'], 'sha256' => $source['sha256'],
        'observed_at' => $source['observed_at'], 'published_on' => null, 'effective_from' => null,
        'proven_effective_scope' => 'Selected SP2660/1.01 PJ general ISS price/rate context;2026 reviewed material scope,not whole-act approval.'];
}
$bundle['notice'] = 'Integrated local per-document ISS133 with fresh proof transport134;document fulfillment and other profiles remain independent.';
$write('bundles/consumption-phase4b-'.$version.'.json', $bundle);
echo json_encode(['bundle' => $bundle['bundle_id'], 'official_sources' => count($bundle['official_sources'])]).PHP_EOL;
