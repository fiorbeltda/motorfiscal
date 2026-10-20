<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use App\Modules\Fiscal\ConsumptionReform\MaterialInputPolicy119;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

$root = dirname(__DIR__).'/resources/fiscal';
$version = MaterialInputPolicy119::VERSION;
$paths = ['catalog' => 'catalogs/rtc-codes-'.$version.'.json',
    'controls' => 'production/phase4b-controls-'.$version.'.json',
    'bundle' => 'bundles/consumption-phase4b-'.$version.'.json'];
foreach ($paths as $path) {
    if (file_exists($root.'/'.$path)) {
        throw new RuntimeException('Exclusive generation already exists: '.$path);
    }
}
$read = static fn (string $path): array => json_decode((string) file_get_contents($root.'/'.$path), true, 512, JSON_THROW_ON_ERROR);
$bundle = $read('bundles/consumption-phase4b-2026.10.20-116-draft2.json');
$codes = $read($bundle['resource_paths']['rtc_code_catalog']);
$controls = $read($bundle['production_controls']['path']);
$sourceFile = 'input119-lc214-compilado-observed117.html';
$sourceHash = MaterialInputPolicy119::policy()['source']['sha256'];
if (hash_file('sha256', $root.'/sources/official/'.$sourceFile) !== $sourceHash) {
    throw new RuntimeException('Official source117 bytes missing or changed');
}
$available = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.uP');
if ($available < MaterialInputPolicy119::AVAILABLE_AT) {
    throw new RuntimeException('Build cannot precede observed source');
}
$codes['version'] = $version;
$codes['material_input_policy119'] = MaterialInputPolicy119::policy();
$controls['version'] = $version;
$controls['controls'][] = ['id' => '4B.4.MATERIAL_INPUT119', 'state' => 'PARTIAL',
    'source_ids' => ['INPUT119_LC214_OBSERVED117'],
    'gap' => 'Local exact-type/evidence/line-identity and collection-reference guards119;not proof of authenticity,legal applicability,payment or complete4B. Full regression and independent acceptance pending.'];
$bundle['predecessor_bundle_id'] = $bundle['bundle_id'];
$bundle['bundle_id'] = 'CONSUMPTION-PHASE4B-'.$version;
$bundle['version'] = $version;
$bundle['knowledge_available_at'] = $available;
$bundle['catalog_versions']['rtc_codes'] = $version;
$bundle['resource_paths']['rtc_code_catalog'] = $paths['catalog'];
$bundle['content_hashes']['rtc_code_catalog'] = CanonicalJson::hash($codes);
$bundle['production_controls'] = ['path' => $paths['controls'], 'sha256' => CanonicalJson::hash($controls)];
$bundle['notice'] = 'Mandatory source-bound input119 guards;historical bundles unchanged. Local validation only,no full119 acceptance or production approval.';
$bundle['official_sources'][] = ['id' => 'INPUT119_LC214_OBSERVED117',
    'url' => MaterialInputPolicy119::policy()['source']['url'], 'file' => $sourceFile,
    'sha256' => $sourceHash, 'version' => 'OBSERVED_2026.10.20_CAPTURE117',
    'observed_at' => MaterialInputPolicy119::AVAILABLE_AT,
    'observed_at_raw' => MaterialInputPolicy119::policy()['source']['observed_at_raw'],
    'observation_precision_note' => 'Conservative microsecond ceiling of preserved capture completion;raw100ns observation retained,not a new capture.',
    'published_on' => null, 'effective_from' => null,
    'proven_effective_scope' => 'Articles12,27II,31-36 reference/input consistency only;not historical validity,legal approval or payment.'];
foreach (['catalog' => $codes, 'controls' => $controls, 'bundle' => $bundle] as $key => $value) {
    $handle = fopen($root.'/'.$paths[$key], 'x');
    if ($handle === false) {
        throw new RuntimeException('Exclusive destination unavailable');
    }
    fwrite($handle, json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
    fclose($handle);
}
$captured = (new FiscalEngineFactory)->captureKnowledge($root, $paths['bundle']);
echo json_encode(['status' => 'INPUT119_BUNDLE_INSTALLED_NOT_FULL_ACCEPTANCE',
    'bundle' => $paths['bundle'], 'available_at' => $available,
    'bundle_raw_sha256' => hash_file('sha256', $root.'/'.$paths['bundle']),
    'bundle_canonical_sha256' => CanonicalJson::hash($bundle),
    'captured_bundle_id' => $captured['rule_bundle_id'],
    'official_sources' => count($bundle['official_sources']), 'production_ready' => false], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n";
