<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

use RuntimeException;

final readonly class CtribNacCatalogImporter
{
    private const SHEET = 'LISTA.SERV.NAC.';

    public function __construct(private RestrictedXlsxReader $reader = new RestrictedXlsxReader) {}

    public function import(string $path): CtribNacImportReport
    {
        $rows = $this->reader->readWorksheet($path, self::SHEET);
        $header = array_shift($rows);
        $expected = [
            'A' => 'CÓDIGO DE TRIBUTAÇÃO NACIONAL',
            'B' => 'ITEM',
            'C' => 'SUBITEM',
            'D' => 'DESDOBRO NACIONAL',
            'E' => 'DESCRIÇÃO',
        ];
        if (! is_array($header)) {
            throw new RuntimeException('cTribNac worksheet is empty.');
        }
        foreach ($expected as $column => $label) {
            if (($header[$column] ?? null) !== $label) {
                throw new RuntimeException("Unexpected cTribNac header in column {$column}.");
            }
        }

        $entries = [];
        $issues = [];
        $seen = [];
        foreach ($rows as $offset => $row) {
            $rawCode = trim($row['A'] ?? '');
            if ($rawCode === '') {
                continue;
            }
            $line = $offset + 2;
            try {
                $item = $this->integerCell($row['B'] ?? '', 'ITEM', $line);
                $subitem = $this->integerCell($row['C'] ?? '', 'SUBITEM', $line);
                $split = $this->integerCell($row['D'] ?? '', 'DESDOBRO NACIONAL', $line);
            } catch (RuntimeException $exception) {
                $issues[] = new ImportIssue('INVALID_CTRIBNAC_DIMENSION', $exception->getMessage(), basename($path), $line);

                continue;
            }
            $code = sprintf('%02d%02d%02d', $item, $subitem, $split);
            if (preg_match('/^\d{1,6}$/D', $rawCode) !== 1 || str_pad($rawCode, 6, '0', STR_PAD_LEFT) !== $code) {
                $issues[] = new ImportIssue('CTRIBNAC_CODE_MISMATCH', "Code {$rawCode} does not match its dimensions ({$code}).", basename($path), $line);

                continue;
            }
            if (isset($seen[$code])) {
                $issues[] = new ImportIssue('DUPLICATE_CTRIBNAC', "Duplicate cTribNac code: {$code}", basename($path), $line);

                continue;
            }
            $description = trim(preg_replace('/\s+/u', ' ', $row['E'] ?? '') ?? ($row['E'] ?? ''));
            if ($description === '') {
                $issues[] = new ImportIssue('EMPTY_CTRIBNAC_DESCRIPTION', "cTribNac code {$code} has no description.", basename($path), $line);

                continue;
            }
            $seen[$code] = true;
            $special = $code === '990101';
            $entries[] = new NationalServiceCodeEntry(
                code: $code,
                formattedCode: substr($code, 0, 2).'.'.substr($code, 2, 2).'.'.substr($code, 4, 2),
                itemCode: str_pad((string) $item, 2, '0', STR_PAD_LEFT),
                subitemCode: str_pad((string) $subitem, 2, '0', STR_PAD_LEFT),
                splitCode: str_pad((string) $split, 2, '0', STR_PAD_LEFT),
                lc116Code: $special ? null : sprintf('%02d.%02d', $item, $subitem),
                officialDescription: $description,
                specialNoIss: $special,
                status: 'ACTIVE',
                sourceSheet: self::SHEET,
            );
        }

        return new CtribNacImportReport($entries, $issues);
    }

    private function integerCell(string $value, string $label, int $line): int
    {
        if (preg_match('/^(0|[1-9]\d*)$/D', trim($value)) !== 1) {
            throw new RuntimeException("{$label} is not an integer at worksheet row {$line}.");
        }

        return (int) $value;
    }
}
