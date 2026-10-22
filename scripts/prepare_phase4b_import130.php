<?php

declare(strict_types=1);

use App\Modules\Fiscal\NFSe\DpsImportCandidatePolicy130;

require __DIR__.'/../vendor/autoload.php';
$path = __DIR__.'/../resources/fiscal/projections/import-dps-candidate-2026.10.22-130.json';
if (! is_dir(dirname($path))) {
    mkdir(dirname($path), 0777, true);
}
$file = fopen($path, 'x');
if ($file === false) {
    throw new RuntimeException('Exclusive130 projection policy already exists.');
}
fwrite($file, json_encode(DpsImportCandidatePolicy130::policy(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
fclose($file);
echo hash_file('sha256', $path).PHP_EOL;
