<?php

declare(strict_types=1);

use App\Modules\Fiscal\Infrastructure\Scenario\FiscalEngineFactory;
use App\Modules\Fiscal\Infrastructure\Scenario\RtcScenarioRunService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $execution = (new RtcScenarioRunService(new FiscalEngineFactory))->execute(resource_path('fiscal'), 'bundles/consumption-phase4b-2026.10.08-28-draft38.json', $argv[1]);
    $snapshotJson = json_encode($execution->snapshot(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
    $snapshotSha = hash('sha256', $snapshotJson);
    if (isset($argv[2])) {
        $stream = fopen($argv[2], 'x');
        if ($stream === false) {
            throw new RuntimeException('Refusing to overwrite immutable snapshot.');
        }
        try {
            for ($offset = 0, $length = strlen($snapshotJson); $offset < $length;) {
                $chunk = substr($snapshotJson, $offset, 65536);
                $written = fwrite($stream, $chunk);
                if ($written === false || $written === 0) {
                    throw new RuntimeException('Cannot write snapshot chunk.');
                }
                $offset += $written;
            }
            if (! fflush($stream)) {
                throw new RuntimeException('Cannot flush snapshot.');
            }
        } finally {
            fclose($stream);
        }
        if (hash_file('sha256', $argv[2]) !== $snapshotSha) {
            throw new RuntimeException('Snapshot bytes differ from the standard serializer.');
        }
    }
    unset($snapshotJson);
    $result = $execution->result->toArray();
    $trace = [];
    foreach ($result['trace'] as $row) {
        if ($row['stage'] === 'FINANCIAL_OPERATION_AND_BASE') {
            $base = $row['base'];
            $yield = array_values(array_filter($base['entries'], static fn (array $entry): bool => $entry['id'] === 'yield1'))[0];
            $parent = $base['realization_bindings']['tvm1'];
            $trace[] = ['stage' => $row['stage'], 'base' => ['net_base' => $base['net_base'], 'period_end' => $base['period_end'],
                'deductions_before_art188' => $base['deductions_before_art188'],
                'entries' => [['id' => 'yield1', 'd2101_binding' => $yield['d2101_binding']]],
                'realization_bindings' => ['tvm1' => ['analysis' => $parent['analysis'], 'financial_entries' => $parent['financial_entries'], 'deductions' => $parent['deductions']]]]];
        } elseif ($row['stage'] === 'FINANCIAL_GROSS_DEBITS') {
            $trace[] = $row;
        }
    }
    echo json_encode(['status' => $result['status'], 'reason_codes' => $result['reason_codes'], 'decision_hash' => $result['decision_hash'], 'native_snapshot_sha256' => $snapshotSha, 'trace' => $trace], JSON_THROW_ON_ERROR)."\n";
    exit($result['status'] === 'INVALID' ? 1 : 0);

} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
}
