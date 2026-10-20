<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Fiscal\Core\Evidence\SourceArtifact;
use App\Modules\Fiscal\Infrastructure\Persistence\FiscalSourceRegistry;
use Illuminate\Support\Facades\DB;
use Throwable;

final class RegisterFiscalSourceCommand extends FiscalJsonCommand
{
    protected $signature = 'fiscal:source:register
        {source-key : Stable source identifier referenced by rules or catalogs}
        {version : Immutable source version}
        {path : Original or derived source artifact path}
        {--authority=OFFICIAL_FILE : Authority/source type}
        {--published-on= : Publication date (YYYY-MM-DD)}
        {--effective-from= : Fiscal validity start (YYYY-MM-DD)}
        {--status=REGISTERED_INPUT : Artifact status recorded in its manifest}';

    protected $description = 'Register an immutable fiscal source artifact without importing a catalog';

    public function handle(): int
    {
        try {
            $artifact = SourceArtifact::fromFile(
                (string) $this->argument('source-key'),
                (string) $this->argument('version'),
                (string) $this->argument('path'),
                (string) $this->option('status'),
            );
            $id = (new FiscalSourceRegistry(DB::connection()))->register(
                $artifact,
                (string) $this->option('authority'),
                $this->nullableOption('published-on'),
                $this->nullableOption('effective-from'),
            );
            $this->outputJson([
                'registered' => true,
                'source_version_id' => $id,
                'artifact' => $artifact->manifest(),
            ]);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->outputJson(['status' => 'ERROR', 'error' => $exception->getMessage()]);

            return self::FAILURE;
        }
    }

    private function nullableOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
