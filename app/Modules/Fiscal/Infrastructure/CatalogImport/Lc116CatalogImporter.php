<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

final class Lc116CatalogImporter
{
    public function import(string $path): Lc116ImportReport
    {
        if (! is_file($path)) {
            throw new RuntimeException("LC 116 source not found: {$path}");
        }
        $html = file_get_contents($path);
        if (! is_string($html) || stripos($html, '<!DOCTYPE') === false) {
            throw new RuntimeException('LC 116 source must be a complete local HTML snapshot.');
        }
        if (stripos($html, '<!ENTITY') !== false) {
            throw new RuntimeException('Entities are not permitted in the LC 116 source.');
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            if (! $document->loadHTML($html, LIBXML_NONET | LIBXML_COMPACT)) {
                throw new RuntimeException('Could not parse the LC 116 HTML snapshot.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);
        $paragraphs = $xpath->query('//p');
        if ($paragraphs === false) {
            throw new RuntimeException('Could not inspect LC 116 source paragraphs.');
        }

        $insideList = false;
        $itemTitles = [];
        $entries = [];
        $issues = [];
        $seen = [];
        foreach ($paragraphs as $paragraph) {
            if (! $paragraph instanceof DOMElement) {
                continue;
            }
            $text = $this->normalize($paragraph->textContent);
            if (! $insideList) {
                if (str_contains($text, 'Lista de serviços anexa à Lei Complementar')) {
                    $insideList = true;
                }

                continue;
            }

            if (preg_match('/^(\d{1,2})\.(\d{2})\s*[-–]\s*(.+)$/u', $text, $matches) === 1) {
                $itemCode = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $subitemCode = $matches[2];
                $code = $itemCode.'.'.$subitemCode;
                if (isset($seen[$code])) {
                    $issues[] = new ImportIssue('DUPLICATE_LC116_CODE', "Duplicate LC 116 code: {$code}", basename($path));

                    continue;
                }
                $seen[$code] = true;
                $description = $this->descriptionWithoutLinks($paragraph, $matches[0], $matches[3]);
                $annotations = [];
                foreach ($xpath->query('.//a', $paragraph) ?: [] as $anchor) {
                    if ($anchor instanceof DOMElement) {
                        $annotations[] = [
                            'text' => $this->normalize($anchor->textContent),
                            'href' => $anchor->getAttribute('href') !== '' ? $anchor->getAttribute('href') : null,
                        ];
                    }
                }
                $entries[] = new Lc116ServiceEntry(
                    code: $code,
                    itemCode: $itemCode,
                    subitemCode: $subitemCode,
                    itemLegalTitle: $itemTitles[$itemCode] ?? '',
                    legalDescription: $description,
                    status: preg_match('/^\(VETADO\)/iu', $description) === 1 ? 'VETOED' : 'ACTIVE',
                    sourceAnnotations: $annotations,
                );

                continue;
            }

            if (preg_match('/^(\d{1,2})\s*[-–]\s*(.+)$/u', $text, $matches) === 1) {
                $itemCode = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $itemTitles[$itemCode] = $this->textWithoutLinks($paragraph, $matches[2]);
            }
        }

        if (! $insideList) {
            throw new RuntimeException('LC 116 annex marker was not found in the HTML snapshot.');
        }
        foreach ($entries as $entry) {
            if ($entry->itemLegalTitle === '') {
                $issues[] = new ImportIssue('LC116_ITEM_TITLE_MISSING', "Item title is missing for {$entry->code}.", basename($path));
            }
        }

        return new Lc116ImportReport($entries, $issues);
    }

    private function descriptionWithoutLinks(DOMElement $paragraph, string $fullText, string $fallback): string
    {
        $withoutLinks = $this->textWithoutLinks($paragraph, $fallback);
        if (preg_match('/^\d{1,2}\.\d{2}\s*[-–]\s*(.+)$/u', $withoutLinks, $matches) === 1) {
            return $this->normalize($matches[1]);
        }

        return $this->normalize($fallback !== '' ? $fallback : $fullText);
    }

    private function textWithoutLinks(DOMElement $paragraph, string $fallback): string
    {
        $clone = $paragraph->cloneNode(true);
        if (! $clone instanceof DOMElement) {
            return $this->normalize($fallback);
        }
        $links = $clone->getElementsByTagName('a');
        while ($links->length > 0) {
            $link = $links->item(0);
            if (! $link instanceof DOMElement) {
                break;
            }
            $link->parentNode?->removeChild($link);
        }

        return $this->normalize($clone->textContent);
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $value)) ?? $value);
    }
}
