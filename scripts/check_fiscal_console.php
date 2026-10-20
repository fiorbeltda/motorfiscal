<?php

declare(strict_types=1);

// Standalone checks: no Laravel boot, database, engine execution or fiscal corpus changes.
require_once dirname(__DIR__).'/resources/monitoring/FiscalConsoleSnapshot.php';

use FiscalConsole\FiscalConsoleSnapshot;

$root = sys_get_temp_dir().'/fiscal-console-check-'.bin2hex(random_bytes(6));
$files = [];
$directories = [];
$checks = 0;
$write = function (string $relative, string $content) use ($root, &$files, &$directories): void {
    $path = $root.'/'.$relative;
    $missing = [];
    for ($dir = dirname($path); ! is_dir($dir); $dir = dirname($dir)) {
        $missing[] = $dir;
    }
    foreach (array_reverse($missing) as $dir) {
        mkdir($dir);
        $directories[] = $dir;
    }
    file_put_contents($path, $content);
    $files[$path] = true;
};
$check = function (bool $condition, string $label) use (&$checks): void {
    if (! $condition) {
        throw new RuntimeException($label);
    }
    $checks++;
};
$phase = fn (array $snapshot, string $id): array => array_values(array_filter($snapshot['phases'], fn ($p) => $p['id'] === $id))[0];

try {
    $write('resources/monitoring/roadmap.json', (string) file_get_contents(dirname(__DIR__).'/resources/monitoring/roadmap.json'));
    $observer = new FiscalConsoleSnapshot($root);
    $empty = $observer->collect();
    $check($empty['acceptance'] === null, 'Missing evidence must remain unavailable.');
    $check($empty['task_progress'] === null, 'No invented delivery progress without a record.');
    $write('resources/monitoring/task-progress.json', json_encode(['title' => 'Local task', 'milestones' => [
        ['complete' => true], ['complete' => false], ['complete' => false],
    ], 'percent' => 100], JSON_THROW_ON_ERROR));
    $check($observer->collect()['task_progress']['percent'] === 33, 'Delivery percentage is computed from milestones, not trusted input.');
    $check($observer->collect()['acceptance'] === null, 'Task progress never creates a fiscal acceptance.');
    $check($phase($empty, '4B')['percent'] === null, 'Unknown objectives must not turn into 0% or completion.');
    $check($phase($empty, '3')['percent'] === null, 'Missing closure must not produce 100%.');
    $check($phase($empty, '5')['state'] === 'PLANNED' && $phase($empty, '5')['functional_percent'] === null,
        'Planned phases must not get invented progress.');

    $write('docs/fiscal/phase3-closure.md', 'A Fase 3 do roadmap do motor completo está implementada');
    $write('app/example.php', '<?php // fiscal fixture');
    $bundle = ['bundle_id' => 'TEST', 'version' => 'test'];
    $write('resources/fiscal/bundles/test.json', json_encode($bundle, JSON_THROW_ON_ERROR));
    $controls = [];
    for ($i = 1; $i <= 8; $i++) {
        $controls[] = ['id' => '4B.'.$i.'.EXAMPLE', 'state' => $i === 1 ? 'APPROVED' : 'PARTIAL', 'gap' => 'Example'];
    }
    $assessment = ['bundle_id' => 'TEST', 'ready' => false, 'controls' => $controls, 'external_dependencies' => []];
    $write('storage/app/accepted/test-production.log', json_encode($assessment, JSON_THROW_ON_ERROR));
    $report = ['created_at' => '2026-10-15T10:00:00Z', 'full_suite_complete' => true,
        'full_suite' => ['exit_code' => 0, 'tests' => 10, 'assertions' => 20],
        'bundle_sha256' => hash_file('sha256', $root.'/resources/fiscal/bundles/test.json'),
        'runtime_hashes' => ['app/example.php' => hash_file('sha256', $root.'/app/example.php')],
        'production_ready' => false,
        'final_quality' => ['test-production' => ['command' => ['fiscal:rtc:production-assess', '--bundle-manifest=bundles/test.json'],
            'log_sha256' => hash_file('sha256', $root.'/storage/app/accepted/test-production.log')]]];
    $write('storage/app/accepted/test-development-validation.json', json_encode($report, JSON_THROW_ON_ERROR));
    $write('resources/monitoring/objective-review.json', json_encode(['baseline_bundle' => 'TEST', 'objectives' => [
        ['id' => '4B.2', 'finished' => ['Local delivery'], 'pending' => ['Integral closure'], 'evidence' => []],
    ]], JSON_THROW_ON_ERROR));
    $write('storage/app/bad/bad-development-validation.json', '{incomplete');
    $write('storage/app/failed/failed-development-validation.json', json_encode([...$report,
        'created_at' => '2026-10-16T10:00:00Z', 'full_suite' => ['exit_code' => 1]], JSON_THROW_ON_ERROR));
    $before = [];
    foreach (array_keys($files) as $file) {
        $before[$file] = hash_file('sha256', $file);
    }
    $snapshot = $observer->collect();
    $check($snapshot['acceptance']['tests'] === 10, 'Reject failed/newer and malformed reports.');
    $check($snapshot['bundle'] === 'TEST', 'Use bundle bound by accepted hash.');
    $check($snapshot['integrity']['state'] === 'MATCH', 'Match recorded runtime hashes.');
    $check($phase($snapshot, '3')['percent'] === 100, 'Documented functional closure is recognized.');
    $check($phase($snapshot, '4B')['completed'] === 1 && $phase($snapshot, '4B')['percent'] === 13,
        'Partial controls receive no arbitrary progress weight.');
    $check($phase($snapshot, '4B')['functional_percent'] === null,
        'Formal group closure percentage is not functional implementation percentage.');
    $check($phase($snapshot, '4B')['details'][1]['finished'] === ['Local delivery'],
        'Reviewed functional deliveries appear separately from controls.');
    $check($snapshot['roadmap_summary']['closed_phases'] === 1,
        'Count only documented closed phases, not the foundation or planned phases.');
    foreach ($before as $file => $hash) {
        $check(hash_file('sha256', $file) === $hash, 'Observer must not mutate evidence.');
    }
    $write('app/example.php', '<?php // changed');
    $check($observer->collect()['integrity']['state'] === 'CHANGED', 'Changes after acceptance are visible.');
    $write('resources/monitoring/objective-review.json', json_encode(['baseline_bundle' => 'OTHER', 'objectives' => [
        ['id' => '4B.2', 'finished' => ['Not bound to acceptance']],
    ]], JSON_THROW_ON_ERROR));
    $check($phase($observer->collect(), '4B')['details'][1]['finished'] === [],
        'A review bound to another bundle cannot label the current accepted scope.');
    $write('storage/app/accepted/test-production.log', json_encode([...$assessment, 'controls' => []], JSON_THROW_ON_ERROR));
    $check($phase($observer->collect(), '4B')['percent'] === null, 'Tampered assessment cannot drive progress.');
    echo json_encode(['status' => 'PASS', 'checks' => $checks], JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    // Remove only the exact temporary files and directories created by this check.
    foreach (array_keys($files) as $file) {
        unlink($file);
    }
    foreach (array_reverse($directories) as $dir) {
        rmdir($dir);
    }
}
