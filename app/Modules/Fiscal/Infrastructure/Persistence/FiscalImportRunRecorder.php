<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Persistence;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final readonly class FiscalImportRunRecorder
{
    public function __construct(private ConnectionInterface $database) {}

    /** @param array<string, mixed> $manifest */
    public function start(string $sourceVersionId, string $importer, array $manifest): string
    {
        $id = Str::uuid7()->toString();
        $this->database->table('fiscal_import_runs')->insert([
            'id' => $id,
            'source_version_id' => $sourceVersionId,
            'importer' => $importer,
            'status' => 'RUNNING',
            'started_at' => now(),
            'finished_at' => null,
            'manifest' => CanonicalJson::encode($manifest),
            'report' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /** @param array<string, mixed> $report */
    public function finish(string $runId, string $status, array $report): void
    {
        $this->database->table('fiscal_import_runs')->where('id', $runId)->update([
            'status' => $status,
            'finished_at' => now(),
            'report' => CanonicalJson::encode($report),
            'updated_at' => now(),
        ]);
    }
}
