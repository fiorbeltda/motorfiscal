<?php

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require __DIR__.'/arithmetic156_fixture.php';

$rows = [];
foreach (array_keys(profiles156()) as $profile) {
    foreach ([2026, 2027, 2030, 2033] as $year) {
        [$input, $knowledge] = arithmetic156($profile, $year);
        $result = evaluate156($input, $knowledge);
        $trace = array_column($result['trace'], null, 'stage');
        $rows[] = ['profile' => $profile, 'year' => $year, 'source_kind' => 'SYNTHETIC_TEST',
            'official' => false, 'input' => $input, 'knowledge_hashes' => $knowledge['content_hashes'],
            'result' => $result, 'candidate' => $trace['SYNTHETIC_ARITHMETIC156']];
    }
}
echo json_encode(['contract' => 'A156-ARITHMETIC-EVIDENCE', 'rows' => $rows], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
