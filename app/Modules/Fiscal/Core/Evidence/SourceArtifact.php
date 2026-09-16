<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Evidence;

use RuntimeException;

final readonly class SourceArtifact
{
    public function __construct(
        public string $sourceKey,
        public string $version,
        public string $path,
        public string $sha256,
        public int $size,
        public string $status,
    ) {}

    public static function fromFile(string $sourceKey, string $version, string $path, string $status): self
    {
        if (! is_file($path)) {
            throw new RuntimeException("Source artifact not found: {$path}");
        }

        $hash = hash_file('sha256', $path);
        $size = filesize($path);

        if ($hash === false || $size === false) {
            throw new RuntimeException("Could not inspect source artifact: {$path}");
        }

        return new self($sourceKey, $version, $path, $hash, $size, $status);
    }

    /** @return array<string, mixed> */
    public function manifest(): array
    {
        return [
            'source_key' => $this->sourceKey,
            'version' => $this->version,
            'path' => $this->path,
            'sha256' => $this->sha256,
            'size' => $this->size,
            'status' => $this->status,
        ];
    }
}
