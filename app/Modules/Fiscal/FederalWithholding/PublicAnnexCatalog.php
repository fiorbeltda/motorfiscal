<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\FederalWithholding;

use App\Modules\Fiscal\Core\Shared\DecimalValue;
use InvalidArgumentException;

final readonly class PublicAnnexCatalog
{
    /** @var array<string, array{category:string, ir:string, csll:string, cofins:string, pis:string, total:string, revenue_code:string, locator:string}> */
    private array $rows;

    /** @param array<string, mixed> $document */
    public function __construct(private array $document)
    {
        if (($document['catalog_id'] ?? null) !== 'PUBLIC.IN_RFB_1234.ANNEX_I'
            || ($document['status'] ?? null) !== 'SANDBOX_CANDIDATE'
            || ! is_array($document['rows'] ?? null)) {
            throw new InvalidArgumentException('Invalid public withholding catalog.');
        }
        $rows = [];
        foreach ($document['rows'] as $row) {
            if (! is_array($row)) {
                throw new InvalidArgumentException('Invalid public withholding row.');
            }
            foreach (['category', 'ir', 'csll', 'cofins', 'pis', 'total', 'revenue_code', 'locator'] as $key) {
                if (! isset($row[$key]) || ! is_string($row[$key]) || $row[$key] === '') {
                    throw new InvalidArgumentException("Missing public withholding row field: {$key}");
                }
            }
            $sum = DecimalValue::fromString('0');
            foreach (['ir', 'csll', 'cofins', 'pis'] as $tax) {
                $sum = $sum->plus(DecimalValue::fromString($row[$tax]));
            }
            if ($sum->compareTo(DecimalValue::fromString($row['total'])) !== 0
                || isset($rows[$row['category']])) {
                throw new InvalidArgumentException('Invalid or duplicate public withholding rates.');
            }
            $rows[$row['category']] = [
                'category' => $row['category'],
                'ir' => $row['ir'],
                'csll' => $row['csll'],
                'cofins' => $row['cofins'],
                'pis' => $row['pis'],
                'total' => $row['total'],
                'revenue_code' => $row['revenue_code'],
                'locator' => $row['locator'],
            ];
        }
        if (count($rows) !== 9) {
            throw new InvalidArgumentException('Public withholding Annex I must contain all nine rows.');
        }
        $this->rows = $rows;
    }

    /** @return array{category:string, ir:string, csll:string, cofins:string, pis:string, total:string, revenue_code:string, locator:string}|null */
    public function row(string $category): ?array
    {
        return $this->rows[$category] ?? null;
    }

    /** @return array<string, string|null> */
    public function provenance(): array
    {
        return [
            'catalog_id' => (string) $this->document['catalog_id'],
            'version' => (string) $this->document['version'],
            'source_id' => (string) $this->document['source_id'],
            'source_sha256' => (string) $this->document['source_sha256'],
            'effective_from' => $this->document['effective_from'],
            'effective_to' => $this->document['effective_to'],
            'status' => (string) $this->document['status'],
        ];
    }
}
