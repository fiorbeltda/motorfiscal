<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\ISS\IssDocumentBase133;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require_once __DIR__.'/phase4b_iss133_fixture.php';
$index = json_decode((string) file_get_contents(base_path('tests/Fixtures/price111-native112-index.json')), true, 512, JSON_THROW_ON_ERROR);
$cases = [];
foreach ($index['cases'] as $entry) {
    $fixture = json_decode((string) file_get_contents(base_path($entry['path'])), true, 512, JSON_THROW_ON_ERROR);
    if ($fixture['expected_result']['status'] === 'LOCAL_ISS_DOCUMENT_PRICES_COMPOSED') {
        $cases[] = ['name' => pathinfo($entry['path'], PATHINFO_FILENAME), 'seed_path' => $entry['path'],
            'seed_sha256' => $entry['sha256'], 'reverse_results' => false, 'new_price' => null];
    }
}
$cases[] = [...$cases[0], 'name' => 'technical-rounding-4-31', 'new_price' => '4.31'];
$cases[] = [...$cases[0], 'name' => 'fresh-results-reverse-rtc-roles', 'reverse_results' => true];
$directory = sys_get_temp_dir().'/iss133-native-'.bin2hex(random_bytes(6));
mkdir($directory);
try {
    foreach ($cases as $case) {
        if (hash_file('sha256', base_path($case['seed_path'])) !== $case['seed_sha256']) {
            throw new RuntimeException('Preserved source fixture changed.');
        }
        $transportRefusal = null;
        try {
            $input = phase4bIss133Fixture(pathinfo($case['seed_path'], PATHINFO_FILENAME), $case['new_price'], $case['reverse_results']);
        } catch (RuntimeException $error) {
            if (! in_array($case['name'], ['101-future-revoked-at', '102-future-superseded-at'], true)) {
                throw $error;
            }
            $transportRefusal = $error->getMessage();
            $fixture = json_decode((string) file_get_contents(base_path($case['seed_path'])), true, 512, JSON_THROW_ON_ERROR)['input'];
            $input = ['input111' => array_intersect_key($fixture, array_flip(['input110', 'records', 'proof'])),
                'proofs' => [], 'knowledge_at' => '2026-10-22T18:20:00.000000+00:00', 'policy' => IssDocumentBase133::policy()];
        }
        unset($input['scenario']);
        $before = CanonicalJson::hash($input);
        $first = (new IssDocumentBase133)->calculate($input['input111'], $input['proofs'], $input['knowledge_at'], $input['policy']);
        file_put_contents($directory.'/input.json', json_encode($input, JSON_THROW_ON_ERROR));
        $exit = Artisan::call('fiscal:iss:document-base', ['input' => $directory.'/input.json']);
        $second = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        if ($first !== $second || CanonicalJson::hash($input) !== $before
            || ($exit === 0) !== ($first['status'] === 'LOCAL_ISS_DOCUMENT_BASES_CALCULATED')) {
            throw new RuntimeException('Native component/CLI replay differs.');
        }
        echo json_encode(['case' => $case, 'input' => $input, 'result' => $first,
            'exact_component_cli_replay' => true, 'input_unchanged' => true, 'upstream_transport_refusal' => $transportRefusal],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }
} finally {
    foreach (glob($directory.'/*') ?: [] as $path) {
        unlink($path);
    }
    rmdir($directory);
}
