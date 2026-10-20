<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;

final readonly class DatabaseMunicipalRateIntegrityAnalyzer
{
    public function __construct(private ConnectionInterface $database) {}

    public function analyze(string $sourceVersionId): MunicipalRateIntegrityReport
    {
        $overlaps = 0;
        $gaps = 0;
        $samples = [];
        $previousKey = null;
        $coverageTo = null;
        $coverageIsOpen = false;
        $hasPrevious = false;

        $rows = $this->database->table('municipal_iss_rates')
            ->where('source_version_id', $sourceVersionId)
            ->orderBy('municipality_ibge')
            ->orderBy('service_code')
            ->orderBy('effective_from')
            ->cursor();

        foreach ($rows as $row) {
            $key = "{$row->municipality_ibge}|{$row->service_code}";
            $from = new DateTimeImmutable((string) $row->effective_from);
            $to = $row->effective_to === null ? null : new DateTimeImmutable((string) $row->effective_to);
            if ($key === $previousKey && $hasPrevious) {
                if ($coverageIsOpen) {
                    $overlaps++;
                    $this->sample($samples, 'OVERLAP', $key, $from->format('Y-m-d'));
                } else {
                    $expectedNext = $coverageTo?->modify('+1 day');
                    if ($expectedNext === null) {
                        throw new \LogicException('Closed coverage interval has no end date.');
                    }
                    if ($from < $expectedNext) {
                        $overlaps++;
                        $this->sample($samples, 'OVERLAP', $key, $from->format('Y-m-d'));
                    } elseif ($from > $expectedNext) {
                        $gaps++;
                        $this->sample($samples, 'GAP', $key, $expectedNext->format('Y-m-d'));
                    }
                }
                if (! $coverageIsOpen) {
                    if ($to === null) {
                        $coverageIsOpen = true;
                        $coverageTo = null;
                    } elseif ($coverageTo === null || $to > $coverageTo) {
                        $coverageTo = $to;
                    }
                }
            } else {
                $coverageIsOpen = $to === null;
                $coverageTo = $to;
            }
            $previousKey = $key;
            $hasPrevious = true;
        }

        return new MunicipalRateIntegrityReport($overlaps, $gaps, $samples);
    }

    /** @param list<array<string, string>> $samples */
    private function sample(array &$samples, string $type, string $key, string $date): void
    {
        if (count($samples) < 100) {
            $samples[] = ['type' => $type, 'key' => $key, 'date' => $date];
        }
    }
}
