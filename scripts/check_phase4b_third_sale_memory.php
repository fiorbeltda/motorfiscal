<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use App\Modules\Fiscal\ConsumptionReform\FinancialDebtDailyMemoryCalculator;
use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRecognitionHistoryQualifier;

$root = dirname(__DIR__).'/resources/fiscal/';
$version = '2026.10.08-28-draft37';
$catalog = json_decode(file_get_contents($root.'catalogs/rtc-codes-'.$version.'.json'), true, 512, JSON_THROW_ON_ERROR);
$manifest = json_decode(file_get_contents($root.'bundles/consumption-phase4b-'.$version.'.json'), true, 512, JSON_THROW_ON_ERROR);
$known = new DateTimeImmutable($manifest['knowledge_available_at']);
$rule = $catalog['financial_operation']['base']['securities_realization']['analysis'];
$cases = [];
foreach (glob($root.'scenarios/tvm-third-sale-rounding-'.$version.'/*-current.json') as $path) {
    $fixture = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $scope = ['supplier_id' => $fixture['position']['supplier_id'], 'period' => $fixture['position']['period'], 'title' => $fixture['title'], 'opening' => $fixture['position']['opening']];
    $history = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($scope, $fixture['recognition_history'], $known, $rule['history_policy']);
    if ($history['status'] !== 'RESOLVED' || $history['opening_fiscal_components'] !== $fixture['expected']['opening_fiscal_components']
        || $history['opening_carrying_components'] !== $fixture['expected']['opening_carrying_components']) {
        throw new RuntimeException(basename($path).': '.json_encode($history, JSON_THROW_ON_ERROR));
    }
    $policy = $rule['daily_memory_calculation'];
    $memory = (new FinancialDebtDailyMemoryCalculator)->calculate($fixture['position'], $policy['series'], $known, $policy);
    if ($memory['status'] !== 'RESOLVED') {
        throw new RuntimeException(json_encode($memory, JSON_THROW_ON_ERROR));
    }
    foreach ($fixture['independent_oracle'] as $key => $want) {
        if ($key === 'daily_memory') {
            if (count($memory[$key]) !== count($want)) {
                throw new RuntimeException('Daily row count mismatch.');
            }
            foreach ($want as $index => $row) {
                foreach ($row as $field => $value) {
                    if ($memory[$key][$index][$field] !== $value) {
                        throw new RuntimeException(basename($path).': daily '.$field);
                    }
                }
            }
        } elseif ($memory[$key] !== $want) {
            throw new RuntimeException(basename($path).': '.$key);
        }
    }
    $cases[] = ['case' => basename($path), 'source_snapshot_replayed' => true, 'closing_memory_replayed' => true,
        'september_native_daily_memory_matches_oracle' => true, 'expected_auxiliary_base' => $fixture['expected']['auxiliary_base'],
        'third_sale_financial_decision_captured' => false];
}
if (count($cases) !== 12) {
    throw new RuntimeException('Expected twelve September third-sale preparations.');
}
echo json_encode(['cases' => $cases, 'full_three_month_chain_accepted' => false, 'production_ready' => false], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
