<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

use Illuminate\Database\ConnectionInterface;

final class DatabaseMunicipalIssRateSink implements MunicipalIssRateSink
{
    /** @var list<array<string, mixed>> */
    private array $batch = [];

    private int $received = 0;

    private int $inserted = 0;

    public function __construct(
        private readonly ConnectionInterface $database,
        private readonly string $sourceVersionId,
        private readonly int $batchSize = 1000,
    ) {}

    public function accept(MunicipalIssRateRow $row): void
    {
        $this->received++;
        $timestamp = gmdate('Y-m-d H:i:sP');
        $this->batch[] = $row->toDatabaseRow($this->sourceVersionId, $timestamp);

        if (count($this->batch) >= $this->batchSize) {
            $this->flush();
        }
    }

    public function finish(): ImportSinkSummary
    {
        $this->flush();

        return new ImportSinkSummary($this->inserted, $this->received - $this->inserted);
    }

    private function flush(): void
    {
        if ($this->batch === []) {
            return;
        }

        $this->inserted += $this->database->table('municipal_iss_rates')->insertOrIgnore($this->batch);
        $this->batch = [];
    }
}
