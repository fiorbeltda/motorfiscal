<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use App\Modules\Fiscal\ConsumptionReform\CreditInputPolicy122;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;

$root = dirname(__DIR__).'/resources/fiscal';
$version = CreditInputPolicy122::VERSION;
$paths = ['catalog' => 'catalogs/rtc-codes-'.$version.'.json',
    'controls' => 'production/phase4b-controls-'.$version.'.json',
    'bundle' => 'bundles/consumption-phase4b-'.$version.'.json'];
foreach ($paths as $path) {
    if (file_exists($root.'/'.$path)) {
        throw new RuntimeException('Exclusive generation already exists: '.$path);
    }
}
$read = static fn (string $path): array => json_decode((string) file_get_contents($root.'/'.$path), true, 512, JSON_THROW_ON_ERROR);
$bundle = $read('bundles/consumption-phase4b-2026.10.20-119-draft1.json');
$codes = $read($bundle['resource_paths']['rtc_code_catalog']);
$controls = $read($bundle['production_controls']['path']);
if (hash_file('sha256', $root.'/sources/official/input119-lc214-compilado-observed117.html') !== CreditInputPolicy122::policy()['source']['sha256']) {
    throw new RuntimeException('Preserved official source117 changed');
}
$available = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.uP');
$codes['version'] = $version;
$codes['credit_input_policy122'] = CreditInputPolicy122::policy();
$controls['version'] = $version;
$controls['controls'][] = ['id' => '4B.5.CREDIT_INPUT122', 'state' => 'PARTIAL',
    'source_ids' => ['INPUT119_LC214_OBSERVED117'],
    'gap' => 'Structural credit amount,identifier and line identity guards122;not entitlement,document authenticity,extinction or appropriation. Full122 corpus/regression/acceptance pending.'];
$bundle['predecessor_bundle_id'] = $bundle['bundle_id'];
$bundle['bundle_id'] = 'CONSUMPTION-PHASE4B-'.$version;
$bundle['version'] = $version;
$bundle['knowledge_available_at'] = $available;
$bundle['catalog_versions']['rtc_codes'] = $version;
$bundle['resource_paths']['rtc_code_catalog'] = $paths['catalog'];
$bundle['content_hashes']['rtc_code_catalog'] = CanonicalJson::hash($codes);
$bundle['production_controls'] = ['path' => $paths['controls'], 'sha256' => CanonicalJson::hash($controls)];
$bundle['notice'] = 'Mandatory structural credit input122 guard;inherited119 guards remain required. Historical bundles unchanged;no full122 acceptance or production approval.';
foreach (['catalog' => $codes, 'controls' => $controls, 'bundle' => $bundle] as $key => $value) {
    $handle = fopen($root.'/'.$paths[$key], 'x');
    if ($handle === false) {
        throw new RuntimeException('Exclusive destination unavailable');
    }
    fwrite($handle, json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
    fclose($handle);
}
$captured = (new FiscalEngineFactory)->captureKnowledge($root, $paths['bundle']);
echo json_encode(['status' => 'CREDIT122_INSTALLED_NOT_FULL_ACCEPTANCE', 'bundle_id' => $captured['rule_bundle_id'],
    'available_at' => $available, 'bundle_raw_sha256' => hash_file('sha256', $root.'/'.$paths['bundle']),
    'official_sources' => count($bundle['official_sources']), 'production_ready' => false], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n";
