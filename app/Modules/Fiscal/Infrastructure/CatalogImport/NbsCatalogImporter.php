<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

use RuntimeException;

final class NbsCatalogImporter
{
    public function import(string $path): NbsImportReport
    {
        if (! is_file($path)) {
            throw new RuntimeException("NBS source not found: {$path}");
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Could not open NBS source: {$path}");
        }

        $entries = [];
        $issues = [];
        $currentCode = null;
        $descriptionParts = [];

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim(str_replace("\f", '', $line));

                if (preg_match('/^(1\.\d{4}\.\d{2}\.\d{2})\s+(.+)$/u', $line, $matches) === 1) {
                    $this->flush($entries, $issues, $currentCode, $descriptionParts);
                    $currentCode = $matches[1];
                    $descriptionParts = [trim($matches[2])];

                    continue;
                }

                if ($currentCode !== null && $line !== '' && ! $this->isPageFurniture($line)) {
                    $descriptionParts[] = $line;
                }
            }

            $this->flush($entries, $issues, $currentCode, $descriptionParts);
        } finally {
            fclose($handle);
        }

        $unique = [];
        foreach ($entries as $entry) {
            if (isset($unique[$entry->code])) {
                $issues[] = new ImportIssue('DUPLICATE_NBS_CODE', "Duplicate NBS code {$entry->code}.", basename($path));
            }
            $unique[$entry->code] = $entry;
        }

        return new NbsImportReport(array_values($unique), $issues);
    }

    /**
     * @param  list<NbsCatalogEntry>  $entries
     * @param  list<ImportIssue>  $issues
     * @param  list<string>  $descriptionParts
     */
    private function flush(array &$entries, array &$issues, ?string $code, array $descriptionParts): void
    {
        if ($code === null) {
            return;
        }

        $description = trim(implode(' ', $descriptionParts));
        if ($description === '') {
            $issues[] = new ImportIssue('EMPTY_NBS_DESCRIPTION', "NBS code {$code} has no description.");

            return;
        }

        $entries[] = new NbsCatalogEntry($code, preg_replace('/\s+/u', ' ', $description) ?? $description);
    }

    private function isPageFurniture(string $line): bool
    {
        return str_starts_with($line, '(Fl.')
            || str_starts_with($line, 'ANEXO I')
            || str_starts_with($line, 'Nomenclatura Brasileira')
            || str_starts_with($line, 'Versão 2.0')
            || str_starts_with($line, 'SEÇÃO ')
            || str_starts_with($line, 'Capítulo ');
    }
}
