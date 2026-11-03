<?php

declare(strict_types=1);

// B04: writes the review-only reconciliation report. No engine boot, database or catalog mutation.
// Usage: php scripts/report_phase4b_b04_engine_rule_correlation.php <output.json>

require dirname(__DIR__).'/vendor/autoload.php';

use App\Modules\Fiscal\ConsumptionReform\AnexoViiiCorrelationReferenceB01;
use App\Modules\Fiscal\ConsumptionReform\EngineRuleCorrelationReconcilerB04;

$root = dirname(__DIR__);
$active = json_decode((string) file_get_contents($root.'/resources/fiscal/catalogs/rtc-codes-2026.10.27-141-draft1.json'), true, 512, JSON_THROW_ON_ERROR);
$b01 = json_decode((string) file_get_contents($root.'/'.AnexoViiiCorrelationReferenceB01::CATALOG_PATH), true, 512, JSON_THROW_ON_ERROR);
$bound = (new AnexoViiiCorrelationReferenceB01)->qualify($b01, $root.'/resources/fiscal/sources/official/'.AnexoViiiCorrelationReferenceB01::SOURCE_FILE,
    new DateTimeImmutable('2026-11-03T13:00:00+00:00'));
if ($bound['status'] !== 'RESOLVED') {
    fwrite(STDERR, "B01 reference not bound: {$bound['reason']}\n");
    exit(2);
}
$result = (new EngineRuleCorrelationReconcilerB04)->reconcile($active['engine_decision_rules'],
    ['1.01.00' => $active['correlations'], '1.02.00' => $b01['correlations']]);
$result['inputs'] = ['active_catalog' => 'resources/fiscal/catalogs/rtc-codes-2026.10.27-141-draft1.json',
    'active_catalog_sha256' => hash_file('sha256', $root.'/resources/fiscal/catalogs/rtc-codes-2026.10.27-141-draft1.json'),
    'b01_catalog_sha256' => hash_file('sha256', $root.'/'.AnexoViiiCorrelationReferenceB01::CATALOG_PATH)];
$output = $argv[1] ?? 'php://stdout';
file_put_contents($output, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
fwrite(STDERR, json_encode(['status' => $result['status'], 'report_hash' => $result['report_hash'], 'totals' => $result['report']['totals']], JSON_THROW_ON_ERROR)."\n");
