<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\Files;

use App\Modules\Fiscal\Core\Invariants\FiscalInvariant;
use App\Modules\Fiscal\Core\Invariants\InvariantSeverity;
use App\Modules\Fiscal\Core\Invariants\RequiredDecisionPathInvariant;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use RuntimeException;

final class InvariantDefinitionFileLoader
{
    /**
     * @param  array<int|string, mixed>  $data
     * @return list<FiscalInvariant>
     */
    public function fromArray(array $data): array
    {
        if (! array_is_list($data)) {
            throw new RuntimeException('Invariant definition document must be a JSON list.');
        }
        $invariants = [];
        $ids = [];
        foreach ($data as $row) {
            if (! is_array($row) || ($row['type'] ?? null) !== 'REQUIRED_DECISION_PATH') {
                throw new RuntimeException('Unsupported invariant definition.');
            }
            $id = (string) ($row['id'] ?? '');
            if ($id === '' || isset($ids[$id])) {
                throw new RuntimeException("Duplicate or empty invariant id: {$id}");
            }
            $ids[$id] = true;
            $effectiveFrom = isset($row['effective_from']) ? LocalDate::fromString((string) $row['effective_from'])->value() : null;
            $effectiveTo = isset($row['effective_to']) ? LocalDate::fromString((string) $row['effective_to'])->value() : null;
            if ($effectiveFrom !== null && $effectiveTo !== null && $effectiveFrom > $effectiveTo) {
                throw new RuntimeException("Invalid invariant effective period: {$id}");
            }
            $exemptPath = $row['exempt_when_path'] ?? null;
            $exemptValue = $row['exempt_when_value'] ?? null;
            if (($exemptPath !== null || $exemptValue !== null)
                && (! is_string($exemptPath) || $exemptPath === '' || ! is_string($exemptValue) || $exemptValue === '')) {
                throw new RuntimeException("Incomplete invariant exemption: {$id}");
            }
            $invariants[] = new RequiredDecisionPathInvariant(
                $id,
                (string) ($row['path'] ?? ''),
                InvariantSeverity::from((string) ($row['severity'] ?? 'BLOCKING')),
                $effectiveFrom,
                $effectiveTo,
                $exemptPath,
                $exemptValue,
            );
        }

        return $invariants;
    }
}
