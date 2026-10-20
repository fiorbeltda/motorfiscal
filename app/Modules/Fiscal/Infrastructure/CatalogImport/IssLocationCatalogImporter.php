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
        $declaredRows = null;
        $documentedDivergences = [];
        $seenCodes = [];

        try {
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                if (preg_match('/Total de códigos listados:\s*\*\*(\d+)\*\*/u', $line, $matches) === 1) {
                    $declaredRows = (int) $matches[1];
                }
                if ((str_contains($line, 'Códigos marcados como `LP`') || str_contains($line, 'Código incluído na lista textual de LP'))
                    && preg_match_all('/\b\d{2}\.\d{2}\.\d{2}\b/', $line, $matches) > 0) {
                    array_push($documentedDivergences, ...$matches[0]);
                }
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
                $canonicalCode = str_replace('.', '', $code);
                if (isset($seenCodes[$canonicalCode])) {
                    $issues[] = new ImportIssue('DUPLICATE_CTRIBNAC', "Duplicate cTribNac code: {$code}", basename($markdownPath), $lineNumber);

                    continue;
                }
                $seenCodes[$canonicalCode] = true;

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
                    ctribnacCode: $canonicalCode,
                    description: $cells[2],
                    strategy: $strategy,
                    importOverride: $cells[7] === 'X' ? 'EDEmit' : null,
                    requiredDetailGroup: $cells[8] === '-' ? null : trim($cells[8], '` '),
                );
            }
        } finally {
            fclose($handle);
        }

        if ($declaredRows !== null && count($rows) !== $declaredRows) {
            $issues[] = new ImportIssue('UNEXPECTED_LOCATION_COUNT', "Document declares {$declaredRows} cTribNac rows, found ".count($rows).'.', basename($markdownPath));
        }
        $documentedDivergences = array_values(array_unique($documentedDivergences));
        if ($documentedDivergences !== []) {
            $issues[] = new ImportIssue(
                'SOURCE_INTERNAL_DIVERGENCE',
                'Territorial divergence between source sheets for: '.implode(', ', $documentedDivergences).'.',
                basename($markdownPath),
            );
        }
        if ($declaredRows !== null && $declaredRows !== 338) {
            $issues[] = new ImportIssue(
                'TERRITORIAL_CATALOG_PARTIAL_AGAINST_CTRIBNAC_REFERENCE',
                "Territorial source declares {$declaredRows} codes while the referenced cTribNac catalog declares 338; full coverage is not assumed.",
                basename($markdownPath),
            );
        }

        return new IssLocationImportReport($rows, $issues, $declaredRows, $documentedDivergences);
    }
}
