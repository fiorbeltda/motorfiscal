<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DateTimeImmutable;

/** Observed reference consistency only: never determines a historical fiscal destination. */
final readonly class ObservedGeographicReference115
{
    public const CONTRACT = 'IBGE-OBSERVED-GEOGRAPHY-2026.10.20-115';

    public const OBSERVED_AT = '2026-10-20T11:46:20.034562+00:00';

    public const STATES_SHA256 = '7a4be6136ccc4e108e3676f6c1be87f8eeb14754d202186677a026fd19e7343a';

    public const MUNICIPALITIES_SHA256 = 'c22ce48c3ef95ccd55b7094d357a34b4b7cd7a4fda5ad6ebe6649e7034146689';

    /** @return array<string, mixed> */
    public static function policy(): array
    {
        return ['contract' => self::CONTRACT, 'source_observed_at' => self::OBSERVED_AT,
            'states_sha256' => self::STATES_SHA256, 'municipalities_sha256' => self::MUNICIPALITIES_SHA256,
            'scope' => 'OBSERVED_REFERENCE_CONSISTENCY_NOT_HISTORICAL_FISCAL_LOCATION'];
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function resolve(array $catalog, string $municipality, string $uf, DateTimeImmutable $knownAt, array $policy): array
    {
        if (CanonicalJson::hash($policy) !== CanonicalJson::hash(self::policy())) {
            return $this->stop('SOURCE_POLICY_NOT_BOUND');
        }
        if ($knownAt < new DateTimeImmutable(self::OBSERVED_AT)) {
            return $this->stop('SOURCE_NOT_YET_OBSERVED');
        }
        $expectedSources = [
            'ibge-estados.json' => ['https://servicodados.ibge.gov.br/api/v1/localidades/estados',
                '7ca1368dea3af83cba1af84ae8a7e88f1173c97586831d086cc8b3c1ba9c6596', '2026-10-20T11:46:17.812235+00:00'],
            'ibge-municipios.json' => ['https://servicodados.ibge.gov.br/api/v1/localidades/municipios?orderBy=id',
                '86ecdccdf97d72e7e5e46f0854cfcea8bacc28154cc1bc4ed0e6110e3a6c9c02', '2026-10-20T11:46:20.034562+00:00'],
            'ibge-api-docs.html' => ['https://servicodados.ibge.gov.br/api/docs/localidades',
                'b679b089b03415bbf036519374b5a4f9715a7bbf90fca8edff7c80b5f4cba6b8', '2026-10-20T11:46:18.520671+00:00'],
        ];
        if (($catalog['version'] ?? null) !== 'IBGE-OBSERVED-2026.10.20-115'
            || ($catalog['kind'] ?? null) !== 'OBSERVED_OFFICIAL_GEOGRAPHY_REFERENCE_NOT_FISCAL_LOCATION_RULE'
            || ($catalog['source_observed_at'] ?? null) !== self::OBSERVED_AT
            || ($catalog['historical_validity_proven'] ?? null) !== false
            || ($catalog['fiscal_location_determined'] ?? null) !== false
            || ($catalog['production_ready'] ?? null) !== false
            || ! is_array($catalog['sources'] ?? null) || count($catalog['sources']) !== 3) {
            return $this->stop('SOURCE_PROVENANCE_NOT_BOUND');
        }
        foreach ($expectedSources as $key => [$url, $hash, $observed]) {
            $source = $catalog['sources'][$key] ?? null;
            if (! is_array($source) || ($source['url'] ?? null) !== $url || ($source['sha256'] ?? null) !== $hash
                || ($source['observed_at'] ?? null) !== $observed) {
                return $this->stop('SOURCE_PROVENANCE_NOT_BOUND');
            }
        }
        $states = $catalog['states'] ?? null;
        $rows = $catalog['municipalities'] ?? null;
        if (! is_array($states) || ! is_array($rows) || count($states) !== 27 || count($rows) !== 5571
            || ! hash_equals(self::STATES_SHA256, CanonicalJson::hash($states))
            || ! hash_equals(self::MUNICIPALITIES_SHA256, CanonicalJson::hash($rows))) {
            return $this->stop('SOURCE_ROWS_CHANGED');
        }
        if (preg_match('/\A[0-9]{7}\z/', $municipality) !== 1 || preg_match('/\A[A-Z]{2}\z/', $uf) !== 1) {
            return $this->stop('CODE_SHAPE_INVALID', 'INVALID');
        }
        if (! isset($states[$uf])) {
            return $this->stop('UF_NOT_IN_OBSERVED_REFERENCE', 'INVALID');
        }
        if (substr($municipality, 0, 2) !== $states[$uf]['ibge_uf_code']) {
            return $this->stop('UF_CODE_PREFIX_CONFLICT', 'INVALID');
        }
        if (! isset($rows[$municipality])) {
            return $this->stop('TERRITORIAL_CODE_NOT_IN_OBSERVED_REFERENCE');
        }
        $row = $rows[$municipality];
        if ($row['uf'] !== $uf || $row['ibge_uf_code'] !== $states[$uf]['ibge_uf_code']) {
            return $this->stop('TERRITORIAL_ROW_UF_CONFLICT', 'INVALID');
        }

        return ['status' => 'OBSERVED_GEOGRAPHIC_REFERENCE_CONSISTENT',
            'reason' => 'GEOGRAPHY115_REFERENCE_CONSISTENCY_NOT_FISCAL_DESTINATION',
            'municipality_ibge' => $municipality, 'uf' => $uf, 'territorial_name' => $row['name'],
            'source_row' => $row['source_row'], 'reference_observed_at' => self::OBSERVED_AT,
            'row_hash' => CanonicalJson::hash($row), ...$this->limits()];
    }

    /** @return array<string, bool> */
    private function limits(): array
    {
        return ['historical_validity_proven' => false, 'fiscal_destination_determined' => false,
            'juridical_municipality_status_proven' => false, 'supplier_establishment_proven' => false,
            'acquirer_or_recipient_domicile_proven' => false, 'tax_rate_selected' => false,
            'legal_applicability_approved' => false, 'posting_authorized' => false, 'production_ready' => false];
    }

    /** @return array<string, mixed> */
    private function stop(string $reason, string $status = 'ACTION_REQUIRED'): array
    {
        return ['status' => $status, 'reason' => 'GEOGRAPHY115_'.$reason, ...$this->limits()];
    }
}
