<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

use RuntimeException;

final class IssLocationCatalogImporter
{
    public function import(string $markdownPath): IssLocationImportReport
    {
        if (! is_file($markdownPath)) {
            throw new RuntimeException("ISS location source not found: {$markdownPath}");
        }

        $handle = fopen($markdownPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Could not open ISS location source: {$markdownPath}");
        }

        $rows = [];
        $issues = [];
        $lineNumber = 0;
        $insideCatalogTable = false;

        try {
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                if (str_starts_with(trim($line), '| cTribNac | Descrição | LI |')) {
                    $insideCatalogTable = true;

                    continue;
                }

                if ($insideCatalogTable && str_starts_with(trim($line), '## ')) {
                    break;
                }

                if (! $insideCatalogTable) {
                    continue;
                }

                if (! str_starts_with(ltrim($line), '| `')) {
                    continue;
                }

                $cells = array_map('trim', explode('|', trim($line)));
                if (count($cells) < 11) {
                    $issues[] = new ImportIssue('INVALID_LOCATION_ROW', 'Markdown table row has unexpected columns.', basename($markdownPath), $lineNumber);

                    continue;
                }

                $code = trim($cells[1], '` ');
                if (preg_match('/^\d{2}\.\d{2}\.\d{2}$/', $code) !== 1) {
                    continue;
                }

                $strategyRaw = trim($cells[3], '* ');
                $strategy = match ($strategyRaw) {
                    'EP', 'LP', 'ET' => $strategyRaw,
                    'SEM LI' => null,
                    default => null,
                };

                if ($strategy === null && $strategyRaw !== 'SEM LI') {
                    $issues[] = new ImportIssue('UNKNOWN_LOCATION_STRATEGY', "Unknown strategy: {$strategyRaw}", basename($markdownPath), $lineNumber);
                }

                $rows[] = new IssLocationRuleRow(
                    ctribnacCode: str_replace('.', '', $code),
                    description: $cells[2],
                    strategy: $strategy,
                    importOverride: $cells[7] === 'X' ? 'EDEmit' : null,
                    requiredDetailGroup: $cells[8] === '-' ? null : trim($cells[8], '` '),
                );
            }
        } finally {
            fclose($handle);
        }

        if (count($rows) !== 337) {
            $issues[] = new ImportIssue('UNEXPECTED_LOCATION_COUNT', 'Expected 337 cTribNac rows, found '.count($rows).'.', basename($markdownPath));
        }

        $issues[] = new ImportIssue(
            'SOURCE_INTERNAL_DIVERGENCE',
            'MUN.INCID_INFO.SERV. and RN DPS_NFS-e contain documented territorial divergences; official validation is required.',
            basename($markdownPath),
        );

        return new IssLocationImportReport($rows, $issues);
    }
}
