<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Illuminate\Support\Str;

it('normalizes result and snapshot documents before decision diffing', function (): void {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fiscal-diff-'.Str::uuid()->toString();
    mkdir($directory);
    $resultPath = $directory.DIRECTORY_SEPARATOR.'result.json';
    $snapshotPath = $directory.DIRECTORY_SEPARATOR.'snapshot.json';
    $result = ['status' => 'VALID', 'decision' => ['iss' => ['rate' => '2.00']]];
    file_put_contents($resultPath, CanonicalJson::encode($result));
    file_put_contents($snapshotPath, CanonicalJson::encode([
        'snapshot_version' => '1',
        'input' => [],
        'knowledge' => [],
        'result' => $result,
    ]));

    try {
        $this->artisan('fiscal:decision:diff', [
            'left' => $resultPath,
            'right' => $snapshotPath,
        ])->expectsOutputToContain('"identical": true')->assertSuccessful();
    } finally {
        unlink($resultPath);
        unlink($snapshotPath);
        rmdir($directory);
    }
});
