<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require_once __DIR__.'/phase4b_import132_fixture.php';
[$snapshot, $identity, $document, $bytes] = phase4bImport132Fixture();
$before = CanonicalJson::hash($snapshot);
$directory = sys_get_temp_dir().'/import132-native-'.bin2hex(random_bytes(6));
mkdir($directory);
try {
    foreach (['snapshot' => $snapshot, 'identity' => $identity, 'values' => $document] as $name => $value) {
        file_put_contents($directory.'/'.$name.'.json', json_encode($value, JSON_THROW_ON_ERROR));
    }
    file_put_contents($directory.'/invoice.txt', $bytes);
    $reports = [];
    foreach (['first', 'second'] as $label) {
        $exit = Artisan::call('fiscal:dps:import-candidate', ['snapshot' => $directory.'/snapshot.json',
            'identity' => $directory.'/identity.json', '--values-document' => $directory.'/values.json',
            '--invoice-source' => $directory.'/invoice.txt', '--candidate-output' => $directory.'/'.$label.'.xml']);
        $reports[$label] = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        if ($exit !== 0 || $reports[$label]['status'] !== 'CANDIDATE_VALIDATED'
            || $reports[$label]['document_values132']['status'] !== 'RECONCILED_DECLARED_VALUES') {
            throw new RuntimeException('Native source-bound import candidate refused.');
        }
    }
    if ($before !== CanonicalJson::hash($snapshot)
        || file_get_contents($directory.'/first.xml') !== file_get_contents($directory.'/second.xml')
        || $reports['first']['document_values132'] !== $reports['second']['document_values132']) {
        throw new RuntimeException('Snapshot mutation or nondeterministic documentary reconciliation.');
    }
    echo json_encode(['kind' => 'snapshot', 'case' => 'ordinary', 'runner' => 'integrated', 'version' => '129',
        'replay_matches' => $reports['first']['snapshot_replay_matches'], 'fiscal_snapshot_unchanged' => true,
        'snapshot' => $snapshot], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
    echo json_encode(['kind' => 'xml', 'case' => 'ordinary', 'identity' => $identity,
        'values_document' => $document, 'invoice_source_bytes' => $bytes, 'report' => $reports['first'],
        'validation' => $reports['first']['schema_validation'],
        'first_xml' => file_get_contents($directory.'/first.xml'), 'second_xml' => file_get_contents($directory.'/second.xml')],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
} finally {
    foreach (glob($directory.'/*') ?: [] as $path) {
        unlink($path);
    }
    rmdir($directory);
}
