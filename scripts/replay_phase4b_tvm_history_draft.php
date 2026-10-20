<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRealizationAnalyzer;
use App\Modules\Fiscal\ConsumptionReform\FinancialSecuritiesRecognitionHistoryQualifier;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

try {
    $snapshot = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $policy = json_decode(file_get_contents(dirname(__DIR__).'/resources/fiscal/catalogs/tvm-recognition-history-draft-2026.10.06-28-v2.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($policy['daily_memory_policies'] as $daily) {
        if (! is_string($daily['official_data_file'] ?? null) || preg_match('/^[A-Za-z0-9_.-]+$/', $daily['official_data_file']) !== 1) {
            throw new RuntimeException('Unsafe official daily data filename.');
        }
        $raw = file_get_contents(dirname(__DIR__).'/resources/fiscal/sources/official/'.$daily['official_data_file']);
        if (hash('sha256', $raw) !== $daily['official_data_sha256']) {
            throw new RuntimeException('Observed official daily data hash mismatch.');
        }
        $normalized = [];
        foreach (json_decode($raw, true, 512, JSON_THROW_ON_ERROR) as $row) {
            $date = DateTimeImmutable::createFromFormat('!d/m/Y', $row['data']);
            if ($date === false || $date->format('d/m/Y') !== $row['data']) {
                throw new RuntimeException('Malformed observed daily date.');
            }
            $normalized[] = ['date' => $date->format('Y-m-d'), 'daily_percent' => $row['valor']];
        }
        if (CanonicalJson::encode($normalized) !== CanonicalJson::encode($daily['series']['rows'])) {
            throw new RuntimeException('Official response differs from the captured normalized series.');
        }
    }
    if (($snapshot['input_hash'] ?? null) !== CanonicalJson::hash(array_diff_key($snapshot, ['result' => true, 'input_hash' => true]))) {
        throw new RuntimeException('Draft snapshot input hash mismatch.');
    }
    $known = new DateTimeImmutable($snapshot['known_at']);
    if ($snapshot['contract'] === 'TVM-RECOGNITION-HISTORY-SNAPSHOT-DRAFT-1') {
        if (CanonicalJson::encode($snapshot['rule']) !== CanonicalJson::encode($policy)) {
            throw new RuntimeException('Captured history policy differs from the frozen draft catalog.');
        }
        $result = (new FinancialSecuritiesRecognitionHistoryQualifier)->resolve($snapshot['scope'], $snapshot['history'], $known, $policy);
    } elseif ($snapshot['contract'] === 'TVM-REALIZATION-SNAPSHOT-DRAFT-1') {
        $expectedRule = ['contract' => FinancialSecuritiesRealizationAnalyzer::CONTRACT, 'status' => 'ACTIVE',
            'published_at' => $policy['published_at'], 'source_hash' => $policy['source_hash'],
            'daily_memory_calculation' => $policy['daily_memory_policies']['2026-09'], 'historical_snapshot_required' => true, 'history_policy' => $policy];
        if (CanonicalJson::encode($snapshot['rule']) !== CanonicalJson::encode($expectedRule)) {
            throw new RuntimeException('Captured realization policy differs from the frozen draft contract.');
        }
        $result = (new FinancialSecuritiesRealizationAnalyzer)->analyze($snapshot['input'], $expectedRule, $known);
    } else {
        throw new RuntimeException('Unsupported draft snapshot contract.');
    }
    $matches = CanonicalJson::encode($snapshot['result']) === CanonicalJson::encode($result);
    echo json_encode(['replay_matches' => $matches, 'result_hash' => CanonicalJson::hash($result),
        'result' => $result, 'scope28_completed' => false, 'production_ready' => false], JSON_THROW_ON_ERROR)."\n";
    exit($matches ? 0 : 1);
} catch (Throwable $e) {
    echo json_encode(['status' => 'ERROR', 'error' => $e->getMessage()], JSON_THROW_ON_ERROR)."\n";
    exit(1);
}
