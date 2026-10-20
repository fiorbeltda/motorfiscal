<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Minimal, deliberately restricted OOXML reader for versioned fiscal sources.
 *
 * It reads cell values only. Macros, formulas, external links, formatting and
 * every relationship outside the selected worksheet are never evaluated.
 */
final class RestrictedXlsxReader
{
    private const MAX_ARCHIVE_BYTES = 25_000_000;

    private const MAX_ENTRIES = 256;

    private const MAX_XML_BYTES = 12_000_000;

    /**
     * @return list<array<string, string>> Rows keyed by Excel column letter.
     */
    public function readWorksheet(string $path, string $worksheetName): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("XLSX source not found: {$path}");
        }
        $size = filesize($path);
        if ($size === false || $size > self::MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('XLSX source exceeds the permitted archive size.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException("Could not open XLSX source: {$path}");
        }

        try {
            if ($zip->numFiles > self::MAX_ENTRIES) {
                throw new RuntimeException('XLSX source contains too many archive entries.');
            }

            $workbook = $this->loadXml($this->readEntry($zip, 'xl/workbook.xml'), 'xl/workbook.xml');
            $workbookXPath = $this->spreadsheetXPath($workbook);
            $relationshipId = null;
            foreach ($workbookXPath->query('/s:workbook/s:sheets/s:sheet') ?: [] as $sheet) {
                if ($sheet instanceof DOMElement && $sheet->getAttribute('name') === $worksheetName) {
                    $relationshipId = $sheet->getAttributeNS(
                        'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                        'id',
                    );
                    break;
                }
            }
            if ($relationshipId === null || $relationshipId === '') {
                throw new RuntimeException("Required XLSX worksheet not found: {$worksheetName}");
            }

            $worksheetEntry = $this->resolveWorksheetEntry($zip, $relationshipId);
            $sharedStrings = $this->readSharedStrings($zip);
            $worksheet = $this->loadXml($this->readEntry($zip, $worksheetEntry), $worksheetEntry);
            $worksheetXPath = $this->spreadsheetXPath($worksheet);
            $formulaNodes = $worksheetXPath->query('//s:f');
            if ($formulaNodes === false) {
                throw new RuntimeException("Could not inspect formulas in worksheet {$worksheetName}.");
            }
            if ($formulaNodes->length > 0) {
                throw new RuntimeException("Formulas are not permitted in fiscal worksheet {$worksheetName}.");
            }

            $rows = [];
            foreach ($worksheetXPath->query('/s:worksheet/s:sheetData/s:row') ?: [] as $row) {
                if (! $row instanceof DOMElement) {
                    continue;
                }
                $cells = [];
                foreach ($worksheetXPath->query('./s:c', $row) ?: [] as $cell) {
                    if (! $cell instanceof DOMElement) {
                        continue;
                    }
                    $reference = $cell->getAttribute('r');
                    if (preg_match('/^([A-Z]{1,3})[1-9]\d*$/', $reference, $matches) !== 1) {
                        throw new RuntimeException("Invalid XLSX cell reference: {$reference}");
                    }
                    $cells[$matches[1]] = $this->cellValue($worksheetXPath, $cell, $sharedStrings);
                }
                $rows[] = $cells;
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function resolveWorksheetEntry(ZipArchive $zip, string $relationshipId): string
    {
        $relationships = $this->loadXml(
            $this->readEntry($zip, 'xl/_rels/workbook.xml.rels'),
            'xl/_rels/workbook.xml.rels',
        );
        $xpath = new DOMXPath($relationships);
        $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $relationship = null;
        foreach ($xpath->query('/r:Relationships/r:Relationship') ?: [] as $candidate) {
            if ($candidate instanceof DOMElement && hash_equals($candidate->getAttribute('Id'), $relationshipId)) {
                $relationship = $candidate;
                break;
            }
        }
        if (! $relationship instanceof DOMElement) {
            throw new RuntimeException("Worksheet relationship not found: {$relationshipId}");
        }
        if ($relationship->getAttribute('Type') !== 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet') {
            throw new RuntimeException("Relationship {$relationshipId} is not an OOXML worksheet.");
        }

        $target = $relationship->getAttribute('Target');
        if (preg_match('#^worksheets/[A-Za-z0-9_.-]+\.xml$#D', $target) !== 1) {
            throw new RuntimeException('Worksheet relationship must remain inside xl/worksheets/.');
        }

        return 'xl/'.$target;
    }

    /** @return list<string> */
    private function readSharedStrings(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }
        $document = $this->loadXml($this->readEntry($zip, 'xl/sharedStrings.xml'), 'xl/sharedStrings.xml');
        $xpath = $this->spreadsheetXPath($document);
        $strings = [];
        foreach ($xpath->query('/s:sst/s:si') ?: [] as $item) {
            if (! $item instanceof DOMNode) {
                continue;
            }
            $value = '';
            foreach ($xpath->query('.//s:t', $item) ?: [] as $text) {
                if ($text instanceof DOMNode) {
                    $value .= $text->textContent;
                }
            }
            $strings[] = $value;
        }

        return $strings;
    }

    /** @param list<string> $sharedStrings */
    private function cellValue(DOMXPath $xpath, DOMElement $cell, array $sharedStrings): string
    {
        $type = $cell->getAttribute('t');
        if ($type === 'inlineStr') {
            $value = '';
            foreach ($xpath->query('./s:is//s:t', $cell) ?: [] as $text) {
                if ($text instanceof DOMNode) {
                    $value .= $text->textContent;
                }
            }

            return $value;
        }

        $valueNodeList = $xpath->query('./s:v', $cell);
        $valueNode = $valueNodeList === false ? null : $valueNodeList->item(0);
        $value = $valueNode instanceof DOMNode ? $valueNode->textContent : '';
        if ($type !== 's' || $value === '') {
            return $value;
        }
        if (preg_match('/^(0|[1-9]\d*)$/D', $value) !== 1 || ! array_key_exists((int) $value, $sharedStrings)) {
            throw new RuntimeException("Invalid shared string index: {$value}");
        }

        return $sharedStrings[(int) $value];
    }

    private function readEntry(ZipArchive $zip, string $entry): string
    {
        $stat = $zip->statName($entry);
        if ($stat === false || ! isset($stat['size']) || (int) $stat['size'] > self::MAX_XML_BYTES) {
            throw new RuntimeException("Missing or oversized XLSX entry: {$entry}");
        }
        $content = $zip->getFromName($entry);
        if (! is_string($content)) {
            throw new RuntimeException("Could not read XLSX entry: {$entry}");
        }

        return $content;
    }

    private function loadXml(string $xml, string $entry): DOMDocument
    {
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw new RuntimeException("DTD and entities are not permitted in XLSX entry: {$entry}");
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
                throw new RuntimeException("Invalid XML in XLSX entry: {$entry}");
            }

            return $document;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function spreadsheetXPath(DOMDocument $document): DOMXPath
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return $xpath;
    }
}
