<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class TemporalRateCatalog
{
    /** @param array<string, mixed> $catalog */
    public function __construct(private array $catalog) {}

    /** @return array{status: string, row?: array<string, mixed>, reason: string} */
    public function resolve(string $tax, ?string $jurisdiction, LocalDate $on, DateTimeImmutable $knownAt): array
    {
        $matches = [];
        foreach ($this->catalog['rates'] ?? [] as $row) {
            if (($row['tax'] ?? null) !== $tax || ($row['jurisdiction'] ?? null) !== $jurisdiction) {
                continue;
            }
            if (! isset($row['valid_from'], $row['valid_to'], $row['published_at'], $row['source_id'], $row['source_hash'])) {
                continue;
            }
            if ($on->value() < $row['valid_from'] || $on->value() > $row['valid_to'] || $knownAt < new DateTimeImmutable($row['published_at'])) {
                continue;
            }
            if (isset($row['revoked_at']) && $knownAt >= new DateTimeImmutable($row['revoked_at'])) {
                continue;
            }
            if (isset($row['superseded_at']) && $knownAt >= new DateTimeImmutable($row['superseded_at'])) {
                continue;
            }
            if (($row['status'] ?? null) !== 'ACTIVE' || ! preg_match('/^\d+(?:\.\d+)?$/', (string) ($row['percent'] ?? ''))) {
                continue;
            }
            $matches[] = $row;
        }
        if (count($matches) > 1) {
            return ['status' => 'CONFLICT', 'reason' => 'RTC_RATE_CONFLICT:'.$tax];
        }
        if ($matches === []) {
            return ['status' => 'NOT_FOUND', 'reason' => 'RTC_RATE_NOT_PROVEN:'.$tax];
        }

        return ['status' => 'RESOLVED', 'row' => $matches[0], 'reason' => 'RTC_RATE_RESOLVED'];
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        if (! isset($this->catalog['version'])) {
            throw new InvalidArgumentException('Rate catalog has no version.');
        }

        return ['version' => $this->catalog['version']];
    }
}
