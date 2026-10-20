<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Scenario;

use RuntimeException;

final class JsonFileWriter
{
    /** @param array<string, mixed> $payload */
    public function write(string $path, array $payload): void
    {
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create directory: {$directory}");
        }
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
        $stream = @fopen($path, 'x');
        if ($stream === false) {
            throw new RuntimeException("Refusing to overwrite immutable JSON file: {$path}");
        }
        try {
            if (fwrite($stream, $json) !== strlen($json) || ! fflush($stream)) {
                throw new RuntimeException("Unable to write JSON file: {$path}");
            }
        } finally {
            fclose($stream);
        }
    }
}
