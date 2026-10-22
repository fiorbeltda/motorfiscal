<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require_once __DIR__.'/phase4b_import130_fixture.php';
[$snapshot, $identity] = phase4bImport130Fixture();
$before = CanonicalJson::hash($snapshot);
$directory = sys_get_temp_dir().'/import130-native-'.bin2hex(random_bytes(6));
mkdir($directory);
try {
    file_put_contents($directory.'/snapshot.json', json_encode($snapshot, JSON_THROW_ON_ERROR));
    file_put_contents($directory.'/identity.json', json_encode($identity, JSON_THROW_ON_ERROR));
    $reports = [];
    foreach (['first', 'second'] as $label) {
        $exit = Artisan::call('fiscal:dps:import-candidate', ['snapshot' => $directory.'/snapshot.json',
            'identity' => $directory.'/identity.json', '--candidate-output' => $directory.'/'.$label.'.xml']);
        $reports[$label] = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        if ($exit !== 0 || $reports[$label]['status'] !== 'CANDIDATE_VALIDATED') {
            throw new RuntimeException('Native import candidate refused: '.json_encode($reports[$label]));
        }
    }
    if ($before !== CanonicalJson::hash($snapshot)
        || file_get_contents($directory.'/first.xml') !== file_get_contents($directory.'/second.xml')) {
        throw new RuntimeException('Snapshot mutation or nondeterministic candidate.');
    }
    echo json_encode(['kind' => 'snapshot', 'case' => 'ordinary', 'runner' => 'integrated', 'version' => '129',
        'replay_matches' => $reports['first']['snapshot_replay_matches'], 'fiscal_snapshot_unchanged' => true,
        'snapshot' => $snapshot], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
    echo json_encode(['kind' => 'xml', 'case' => 'ordinary', 'identity' => $identity, 'report' => $reports['first'],
        'validation' => $reports['first']['schema_validation'],
        'first_xml' => file_get_contents($directory.'/first.xml'), 'second_xml' => file_get_contents($directory.'/second.xml')],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
} finally {
    foreach (glob($directory.'/*') ?: [] as $path) {
        unlink($path);
    }
    rmdir($directory);
}
