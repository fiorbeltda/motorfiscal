<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

use App\Modules\Fiscal\Core\Shared\LocalDate;
use RuntimeException;
use ZipArchive;

final class MunicipalIssRateImporter
{
    private const EXPECTED_HEADER = [
        'codigo_ibge',
        'uf',
        'nome_municipio',
        'codigo_servico',
        'incidencia',
        'aliquota',
        'dt_ini',
        'dt_fim',
    ];

    public function import(string $zipPath, MunicipalIssRateSink $sink): MunicipalIssRateImportReport
    {
        if (! is_file($zipPath)) {
            throw new RuntimeException("Municipal rate ZIP not found: {$zipPath}");
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("Could not open ZIP: {$zipPath}");
        }

        $files = 0;
        $readRows = 0;
        $acceptedRows = 0;
        $rejectedRows = 0;
        $blankRates = 0;
        $municipalities = [];
        $serviceCodes = [];
        $issues = [];
        $logicalHash = hash_init('sha256');

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                if ($name === false || ! str_ends_with(strtolower($name), '.csv')) {
                    continue;
                }

                $stream = $zip->getStream($name);
                if ($stream === false) {
                    $this->addIssue($issues, new ImportIssue('ZIP_ENTRY_UNREADABLE', 'Could not read ZIP entry.', $name));

                    continue;
                }

                $files++;

                try {
                    $header = fgetcsv($stream, null, ';', '"', '');
                    if (! is_array($header)) {
                        $this->addIssue($issues, new ImportIssue('MISSING_HEADER', 'CSV entry has no header.', $name, 1));

                        continue;
                    }

                    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? (string) $header[0];
                    if ($header !== self::EXPECTED_HEADER) {
                        $this->addIssue($issues, new ImportIssue('INVALID_HEADER', 'CSV header does not match the expected contract.', $name, 1));

                        continue;
                    }

                    $line = 1;
                    while (($columns = fgetcsv($stream, null, ';', '"', '')) !== false) {
                        $line++;
                        if ($columns === [null]) {
                            continue;
                        }

                        $readRows++;
                        $row = $this->parseRow($columns, $name, $line, $issues);
                        if ($row === null) {
                            $rejectedRows++;

                            continue;
                        }

                        if ($row->rate === null) {
                            $blankRates++;
                        }

                        $municipalities[$row->municipalityIbge] = true;
                        $serviceCodes[$row->serviceCode] = true;
                        hash_update($logicalHash, $row->logicalHash()."\n");
                        $sink->accept($row);
                        $acceptedRows++;
                    }
                } finally {
                    fclose($stream);
                }
            }
        } finally {
            $zip->close();
        }

        return new MunicipalIssRateImportReport(
            files: $files,
            readRows: $readRows,
            acceptedRows: $acceptedRows,
            rejectedRows: $rejectedRows,
            blankRates: $blankRates,
            municipalities: count($municipalities),
            serviceCodes: count($serviceCodes),
            logicalHash: hash_final($logicalHash),
            sink: $sink->finish(),
            issues: $issues,
        );
    }

    /**
     * @param  array<int, string|null>  $columns
     * @param  list<ImportIssue>  $issues
     */
    private function parseRow(array $columns, string $entry, int $line, array &$issues): ?MunicipalIssRateRow
    {
        if (count($columns) !== count(self::EXPECTED_HEADER)) {
            $this->addIssue($issues, new ImportIssue('INVALID_COLUMN_COUNT', 'Row must contain exactly eight columns.', $entry, $line));

            return null;
        }

        $municipalityIbge = trim((string) $columns[0]);
        $uf = trim((string) $columns[1]);
        $municipalityName = trim((string) $columns[2]);
        $serviceCode = trim((string) $columns[3]);
        $incidence = trim((string) $columns[4]);
        $rawRate = trim((string) $columns[5]);
        $rawFrom = trim((string) $columns[6]);
        $rawTo = trim((string) $columns[7]);

        if (preg_match('/^\d{7}$/', $municipalityIbge) !== 1 || preg_match('/^[A-Z]{2}$/', $uf) !== 1 || preg_match('/^\d{2}\.\d{2}\.\d{2}\.\d{3}$/', $serviceCode) !== 1) {
            $this->addIssue($issues, new ImportIssue('INVALID_KEY_FIELDS', 'Municipality IBGE, UF or service code is invalid.', $entry, $line));

            return null;
        }

        try {
            $effectiveFrom = LocalDate::fromString(substr($rawFrom, 0, 10));
            $effectiveTo = $rawTo === '' ? null : LocalDate::fromString(substr($rawTo, 0, 10));
        } catch (\InvalidArgumentException $exception) {
            $this->addIssue($issues, new ImportIssue('INVALID_VALIDITY', $exception->getMessage(), $entry, $line));

            return null;
        }

        if ($effectiveTo !== null && $effectiveTo->isBefore($effectiveFrom)) {
            $this->addIssue($issues, new ImportIssue('INVERTED_VALIDITY', 'dt_fim is before dt_ini.', $entry, $line));

            return null;
        }

        $rate = $this->normalizeRate($rawRate);
        if ($rawRate !== '' && $rate === null) {
            $this->addIssue($issues, new ImportIssue('INVALID_RATE', 'Rate is not a locale-aware decimal.', $entry, $line));

            return null;
        }

        $serviceSegments = explode('.', $serviceCode);

        return new MunicipalIssRateRow(
            municipalityIbge: $municipalityIbge,
            uf: $uf,
            municipalityName: $municipalityName,
            serviceCode: $serviceCode,
            ctribnacCode: $serviceSegments[0].$serviceSegments[1].$serviceSegments[2],
            municipalServiceCode: $serviceSegments[3],
            incidenceRaw: $incidence === '' ? null : $incidence,
            rate: $rate,
            effectiveFrom: $effectiveFrom,
            effectiveTo: $effectiveTo,
            rawRowHash: hash('sha256', implode(';', array_map(fn (?string $value): string => (string) $value, $columns))),
        );
    }

    private function normalizeRate(string $rate): ?string
    {
        if ($rate === '') {
            return null;
        }

        $normalized = str_replace(',', '.', $rate);

        return preg_match('/^\d+(?:\.\d+)?$/', $normalized) === 1 ? $normalized : null;
    }

    /** @param list<ImportIssue> $issues */
    private function addIssue(array &$issues, ImportIssue $issue): void
    {
        if (count($issues) < 100) {
            $issues[] = $issue;
        }
    }
}
