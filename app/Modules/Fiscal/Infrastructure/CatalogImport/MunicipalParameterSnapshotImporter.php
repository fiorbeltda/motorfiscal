<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Infrastructure\CatalogImport;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\ISS\IssResponsibility;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

final class MunicipalParameterSnapshotImporter
{
    /** @return array<string, mixed> */
    public function import(string $path, ?ConnectionInterface $database = null, ?string $sourceVersionId = null): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Municipal parameter snapshot not found: {$path}");
        }
        try {
            $document = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Invalid municipal parameter snapshot JSON: {$path}", previous: $exception);
        }
        if (! is_array($document)) {
            throw new RuntimeException('Municipal parameter snapshot must be a JSON object or list.');
        }
        $rows = array_is_list($document) ? $document : ($document['parameters'] ?? null);
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new RuntimeException('Municipal parameter snapshot must contain a parameters list.');
        }
        if (($database === null) !== ($sourceVersionId === null)) {
            throw new RuntimeException('Database and source version must be provided together.');
        }

        $accepted = 0;
        $inserted = 0;
        $issues = [];
        foreach ($rows as $index => $row) {
            if (! is_array($row) || array_is_list($row)) {
                $issues[] = ['row' => $index + 1, 'code' => 'INVALID_ROW', 'message' => 'Row must be a JSON object.'];

                continue;
            }
            try {
                $payload = $this->normalize($row);
            } catch (Throwable $exception) {
                $issues[] = ['row' => $index + 1, 'code' => 'INVALID_PARAMETER', 'message' => $exception->getMessage()];

                continue;
            }
            $accepted++;
            if ($database === null || $sourceVersionId === null) {
                continue;
            }
            $payloadHash = CanonicalJson::hash($payload);
            $inserted += $database->table('municipal_parameter_snapshots')->insertOrIgnore([
                'id' => Str::uuid7()->toString(),
                'municipality_ibge' => $payload['municipality_ibge'],
                'service_code' => $payload['ctribnac_code'],
                'competence_date' => $payload['competence_date'],
                'effective_from' => $payload['effective_from'],
                'effective_to' => $payload['effective_to'],
                'source_type' => $payload['source_type'],
                'source_version_id' => $sourceVersionId,
                'fetched_at' => $payload['fetched_at'],
                'payload' => CanonicalJson::encode($payload),
                'payload_hash' => $payloadHash,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'input_rows' => count($rows),
            'accepted_rows' => $accepted,
            'rejected_rows' => count($rows) - $accepted,
            'inserted_rows' => $inserted,
            'issues' => $issues,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalize(array $row): array
    {
        $municipality = (string) ($row['municipality_ibge'] ?? '');
        $service = (string) ($row['ctribnac_code'] ?? '');
        if (preg_match('/^\d{7}$/', $municipality) !== 1) {
            throw new RuntimeException('municipality_ibge must contain seven digits.');
        }
        if (preg_match('/^\d{6}$/', $service) !== 1) {
            throw new RuntimeException('ctribnac_code must contain six digits.');
        }
        $competence = LocalDate::fromString((string) ($row['competence_date'] ?? ''))->value();
        $effectiveFrom = isset($row['effective_from'])
            ? LocalDate::fromString((string) $row['effective_from'])->value()
            : $competence;
        $effectiveTo = isset($row['effective_to'])
            ? LocalDate::fromString((string) $row['effective_to'])->value()
            : null;
        if ($effectiveTo !== null && $effectiveTo < $effectiveFrom) {
            throw new RuntimeException('effective_to cannot precede effective_from.');
        }

        $rate = null;
        if (isset($row['rate'])) {
            $rateValue = DecimalValue::fromString((string) $row['rate']);
            if ($rateValue->compareTo(DecimalValue::fromString('0')) < 0 || $rateValue->compareTo(DecimalValue::fromString('100')) > 0) {
                throw new RuntimeException('rate must be between 0 and 100 percent.');
            }
            $rate = $rateValue->rounded(6)->value();
        }
        $responsibility = null;
        if (isset($row['responsibility'])) {
            $responsibility = IssResponsibility::tryFrom((string) $row['responsibility']);
            if ($responsibility === null) {
                throw new RuntimeException('responsibility must be PROVIDER, CUSTOMER or INTERMEDIARY.');
            }
        }
        if ($rate === null && $responsibility === null) {
            throw new RuntimeException('At least rate or responsibility must be present.');
        }

        $fetchedAt = isset($row['fetched_at']) && is_string($row['fetched_at']) && $row['fetched_at'] !== ''
            ? (new \DateTimeImmutable($row['fetched_at']))->format(DATE_ATOM)
            : now()->format(DATE_ATOM);

        return [
            'municipality_ibge' => $municipality,
            'ctribnac_code' => $service,
            'competence_date' => $competence,
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'rate' => $rate,
            'responsibility' => $responsibility?->value,
            'source_type' => (string) ($row['source_type'] ?? 'MUNICIPAL_API'),
            'fetched_at' => $fetchedAt,
            'legal_basis' => isset($row['legal_basis']) && is_string($row['legal_basis']) ? $row['legal_basis'] : null,
            'rule_id' => isset($row['rule_id']) && is_string($row['rule_id']) ? $row['rule_id'] : null,
        ];
    }
}
