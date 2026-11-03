<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DateTimeImmutable;

/**
 * B01: referência local do Anexo VIII v1.02.00 (portal NFS-e, 26/10/2026).
 *
 * A correlação oficial é provisória e expressamente sem regras de negócio no
 * portal; aqui ela só pode ser consultada como referência literal. Uma linha
 * encontrada não seleciona cClassTrib, cIndOp, tratamento ou destino, e a
 * ausência de linha não significa ausência de incidência. O catálogo não é
 * ligado a nenhum bundle e não substitui a materialização 1.01.00.
 */
final readonly class AnexoViiiCorrelationReferenceB01
{
    public const VERSION = 'anexoviii-correlation-1.02.00-b01';

    public const CATALOG_PATH = 'resources/fiscal/catalogs/rtc-correlation-anexoviii-v1.02.00-b01.json';

    public const CATALOG_SHA256 = '839607a105cd8cf22c85c9f090a1d7aacf3591c908a3e4b8d24dad88f63e4a48';

    public const SOURCE_FILE = 'anexo_viii-tabela-correlacao-v01-02-00-20261026.xlsx';

    public const SOURCE_SHA256 = '12cc80bc92687856b47e961b98cdbaa3f8cae568c099b3ef71994e73482ac7d0';

    public const PREVIOUS_SOURCE_SHA256 = 'a21be0e86b7ae2c0c1cfec4ef0d398b96520345790b50f79e4c5b7d1dfe32fb3';

    /**
     * @param  array<string, mixed>  $catalog
     * @return array<string, mixed>
     */
    public function qualify(array $catalog, string $sourcePath, DateTimeImmutable $knownAt): array
    {
        if (($catalog['version'] ?? null) !== self::VERSION || CanonicalJson::hash($catalog) !== self::CATALOG_SHA256) {
            return $this->stop('CATALOG_NOT_BOUND');
        }
        if (! is_file($sourcePath) || hash_file('sha256', $sourcePath) !== self::SOURCE_SHA256
            || ($catalog['source']['sha256'] ?? null) !== self::SOURCE_SHA256
            || ($catalog['previous']['sha256'] ?? null) !== self::PREVIOUS_SOURCE_SHA256) {
            return $this->stop('SOURCE_BYTES_NOT_BOUND');
        }
        if ($knownAt < new DateTimeImmutable((string) $catalog['source']['available_at'])) {
            return $this->stop('SOURCE_NOT_YET_OBSERVED');
        }
        if ($catalog['decision_authority'] !== false || $catalog['bundle_bound'] !== false) {
            return $this->stop('AUTHORITY_ESCALATION_REFUSED', 'INVALID');
        }
        foreach ($catalog['correlations'] as $entry) {
            if (($entry['kind'] ?? null) !== 'OFFICIAL_CORRELATION' || ($entry['decision_authority'] ?? null) !== false) {
                return $this->stop('AUTHORITY_ESCALATION_REFUSED', 'INVALID');
            }
        }

        return ['status' => 'RESOLVED', 'reason' => 'ANEXOVIII_B01_REFERENCE_BOUND',
            'catalog_hash' => self::CATALOG_SHA256, 'source_hash' => self::SOURCE_SHA256, ...$this->limits()];
    }

    /**
     * Literal lookup by NBS. Several official rows may exist for one NBS.
     *
     * @param  array<string, mixed>  $catalog
     * @return array<string, mixed>
     */
    public function lookupNbs(array $catalog, string $sourcePath, string $nbs, string $environment, DateTimeImmutable $knownAt): array
    {
        $qualified = $this->qualify($catalog, $sourcePath, $knownAt);
        if ($qualified['status'] !== 'RESOLVED') {
            return $qualified;
        }
        // Publication on the portal does not establish an effective date or
        // deployment in any official environment.
        if ($environment !== 'LOCAL_SANDBOX') {
            return $this->stop('OFFICIAL_ENVIRONMENT_EFFECTIVE_DATE_UNPROVEN');
        }
        if (preg_match('/\A[0-9]\.[0-9]{4}\.[0-9]{2}\.[0-9]{2}\z/', $nbs) !== 1) {
            return $this->stop('MALFORMED_NBS', 'INVALID');
        }
        $rows = array_values(array_filter($catalog['correlations'], fn (array $entry): bool => $entry['nbs'] === $nbs));
        if ($rows === []) {
            return ['status' => 'NO_COMPLETE_OFFICIAL_CORRELATION', 'reason' => 'ANEXOVIII_B01_NO_COMPLETE_LITERAL_ROW',
                'nbs' => $nbs, 'absence_is_not_nonincidence' => true, 'catalog_hash' => self::CATALOG_SHA256, ...$this->limits()];
        }

        return ['status' => 'REFERENCE_ROWS_AVAILABLE', 'reason' => 'ANEXOVIII_B01_LITERAL_ROWS_AVAILABLE',
            'nbs' => $nbs, 'environment' => $environment, 'source_version' => '1.02.00',
            'rows' => array_map(fn (array $entry): array => [...$entry, 'source_row_hash' => CanonicalJson::hash($entry)], $rows),
            'catalog_hash' => self::CATALOG_SHA256, 'source_hash' => self::SOURCE_SHA256, ...$this->limits()];
    }

    /** @return array<string, bool> */
    private function limits(): array
    {
        return ['decision_authority' => false, 'tax_treatment_selected' => false, 'cclasstrib_selected' => false,
            'cindop_selected' => false, 'official_effective_date_proven' => false, 'remote_deployment_proven' => false,
            'bundle_bound' => false, 'production_ready' => false];
    }

    /** @return array<string, mixed> */
    private function stop(string $reason, string $status = 'ACTION_REQUIRED'): array
    {
        return ['status' => $status, 'reason' => 'ANEXOVIII_B01_'.$reason, ...$this->limits()];
    }
}
