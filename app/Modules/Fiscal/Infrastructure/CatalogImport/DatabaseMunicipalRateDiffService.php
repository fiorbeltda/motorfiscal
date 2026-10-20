<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

use Illuminate\Database\ConnectionInterface;

final readonly class DatabaseMunicipalRateDiffService
{
    public function __construct(private ConnectionInterface $database) {}

    public function compare(string $previousSourceVersionId, string $currentSourceVersionId): MunicipalRateDiffReport
    {
        $current = $this->database->table('municipal_iss_rates as current')
            ->leftJoin('municipal_iss_rates as previous', function ($join) use ($previousSourceVersionId): void {
                $join->on('previous.validity_key', '=', 'current.validity_key')
                    ->where('previous.source_version_id', '=', $previousSourceVersionId);
            })
            ->where('current.source_version_id', $currentSourceVersionId);

        $added = (clone $current)->whereNull('previous.id')->count();
        $changed = (clone $current)->whereNotNull('previous.id')->whereColumn('current.raw_row_hash', '!=', 'previous.raw_row_hash')->count();
        $unchanged = (clone $current)->whereNotNull('previous.id')->whereColumn('current.raw_row_hash', '=', 'previous.raw_row_hash')->count();
        $removed = $this->database->table('municipal_iss_rates as previous')
            ->leftJoin('municipal_iss_rates as current', function ($join) use ($currentSourceVersionId): void {
                $join->on('current.validity_key', '=', 'previous.validity_key')
                    ->where('current.source_version_id', '=', $currentSourceVersionId);
            })
            ->where('previous.source_version_id', $previousSourceVersionId)
            ->whereNull('current.id')
            ->count();

        return new MunicipalRateDiffReport($added, $changed, $removed, $unchanged);
    }
}
