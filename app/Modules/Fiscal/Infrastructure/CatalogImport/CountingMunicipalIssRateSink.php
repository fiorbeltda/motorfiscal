<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

use RuntimeException;
use SQLite3;
use SQLite3Stmt;

final class CountingMunicipalIssRateSink implements MunicipalIssRateSink
{
    private int $count = 0;

    private int $duplicates = 0;

    private readonly string $databasePath;

    private readonly SQLite3 $database;

    private readonly SQLite3Stmt $insert;

    private bool $finished = false;

    public function __construct()
    {
        $path = tempnam(sys_get_temp_dir(), 'fiscal-import-keys-');
        if ($path === false) {
            throw new RuntimeException('Could not allocate duplicate-detection database.');
        }
        $this->databasePath = $path;
        $this->database = new SQLite3($path);
        $this->database->exec('PRAGMA journal_mode=OFF');
        $this->database->exec('PRAGMA synchronous=OFF');
        $this->database->exec('CREATE TABLE seen (key TEXT PRIMARY KEY) WITHOUT ROWID');
        $this->database->exec('BEGIN TRANSACTION');
        $statement = $this->database->prepare('INSERT OR IGNORE INTO seen (key) VALUES (:key)');
        if (! $statement instanceof SQLite3Stmt) {
            throw new RuntimeException('Could not prepare duplicate-detection statement.');
        }
        $this->insert = $statement;
    }

    public function accept(MunicipalIssRateRow $row): void
    {
        $this->insert->reset();
        $this->insert->clear();
        $this->insert->bindValue(':key', $row->validityKey(), SQLITE3_TEXT);
        $result = $this->insert->execute();
        if ($result === false) {
            throw new RuntimeException('Could not record an import key.');
        }
        $result->finalize();
        if ($this->database->changes() === 1) {
            $this->count++;
        } else {
            $this->duplicates++;
        }
    }

    public function finish(): ImportSinkSummary
    {
        if (! $this->finished) {
            $this->database->exec('COMMIT');
            $this->finished = true;
        }

        return new ImportSinkSummary($this->count, $this->duplicates);
    }

    public function __destruct()
    {
        if (! $this->finished) {
            $this->database->exec('ROLLBACK');
        }
        $this->insert->close();
        $this->database->close();
        if (is_file($this->databasePath)) {
            unlink($this->databasePath);
        }
    }
}
