<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Persistence;

use DateTimeImmutable;

final readonly class PublishedRuleBundle
{
    /**
     * @param  list<array<string, mixed>>  $rules
     */
    public function __construct(
        public string $databaseId,
        public string $key,
        public string $version,
        public string $contentHash,
        public DateTimeImmutable $publishedAt,
        public array $rules,
    ) {}

    public function executionId(): string
    {
        return $this->key.'-'.$this->version;
    }
}
