<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRecognitionHistoryQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

$root = dirname(__DIR__);
$version = '2026.10.08-28-draft37';
$catalog = json_decode(file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-'.$version.'.json'), true, 512, JSON_THROW_ON_ERROR);
$manifest = json_decode(file_get_contents($root.'/resources/fiscal/bundles/consumption-phase4b-'.$version.'.json'), true, 512, JSON_THROW_ON_ERROR);
$known = new DateTimeImmutable($manifest['knowledge_available_at']);
$rule = $catalog['financial_operation']['base']['securities_realization']['analysis']['history_policy'];
$cases = [];
foreach (glob($root.'/resources/fiscal/scenarios/tvm-third-sale-rounding-'.$version.'/*-opening.json') as $path) {
    $fixture = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $result = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($fixture['scope'], $fixture['history'], $known, $rule);
    if ($result['status'] !== 'RESOLVED' || ($result['public_created_rounding_closing_replayed'] ?? null) !== true
        || $result['opening_fiscal_components'] !== $fixture['expected']['opening_fiscal_components']
        || $result['opening_carrying_components'] !== $fixture['expected']['opening_carrying_components']
        || hash_file('sha256', $root.'/'.$fixture['source_path']) !== $fixture['source_sha256']) {
        throw new RuntimeException(basename($path).': '.json_encode($result, JSON_THROW_ON_ERROR));
    }
    $output = $root.'/storage/app/phase4b-realization-third-sale-rounding-draft-20261008-28/'.str_replace('-opening.json', '-opening-result.json', basename($path));
    if (is_file($output)) {
        if (CanonicalJson::encode(json_decode(file_get_contents($output), true, 512, JSON_THROW_ON_ERROR)) !== CanonicalJson::encode($result)) {
            throw new RuntimeException('Opening capture changed.');
        }
    } else {
        $stream = fopen($output, 'x');
        if ($stream === false) {
            throw new RuntimeException('Cannot preserve opening result.');
        }
        fwrite($stream, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n");
        fclose($stream);
    }
    $cases[] = ['case' => basename($path), 'source_snapshot_replayed' => true, 'closing_memory_replayed' => true,
        'fiscal_and_economic_components_match' => true, 'opening_result_sha256' => hash_file('sha256', $output),
        'third_sale_accepted' => false];
}
if (count($cases) !== 6) {
    throw new RuntimeException('Expected six partial source openings.');
}
echo json_encode(['cases' => $cases, 'full_three_month_chain_accepted' => false, 'production_ready' => false], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
