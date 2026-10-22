<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;

/** Local reference compatibility is distinct from legal effect and remote deployment. */
final readonly class CIndOpReference126
{
    public const VERSION = '2026.10.22-126-draft1';

    public const REFERENCE_SHA256 = 'd50657ea5a576b7152ca7108ec4a8c96bbd36d3a7712c5d23466bccb247b968f';

    /** @param array<string, mixed> $catalog */
    public static function required(array $catalog): bool
    {
        return in_array($catalog['version'] ?? null, [self::VERSION, DpsLayoutPolicy127::VERSION, DpsForeignPolicy128::VERSION, ImportResponsibility129::VERSION, DocumentSchedule131::VERSION, DocumentSchedule131::PRIOR_VERSION, DocumentIssIntegration134::VERSION], true)
            || array_key_exists('cindop_reference126', $catalog);
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @return array<string, mixed>
     */
    public function qualify(array $catalog, DateTimeImmutable $knownAt): array
    {
        $reference = $catalog['cindop_reference126'] ?? null;
        if (! is_array($reference) || CanonicalJson::hash($reference) !== self::REFERENCE_SHA256) {
            return $this->stop('SOURCE_POLICY_NOT_BOUND');
        }
        if ($knownAt < new DateTimeImmutable($reference['available_at'])) {
            return $this->stop('SOURCE_NOT_YET_OBSERVED');
        }
        $source = $catalog['sources']['cindop'] ?? null;
        $expected = $reference['sources']['RTC_1_03_00']['source'];
        if (! is_array($source) || ($source['version'] ?? null) !== '1.03.00'
            || ($source['sha256'] ?? null) !== $expected['sha256']
            || ($source['observed_at'] ?? null) !== $expected['observed_at']
            || ($source['url'] ?? null) !== $expected['url']) {
            return $this->stop('SOURCE_PROVENANCE_NOT_BOUND');
        }
        $rows = [];
        foreach ($reference['sources']['RTC_1_03_00']['rows'] as $code => $row) {
            $values = $row['values'];
            $rows[$code] = ['operation' => $values[1] ?? '', 'characteristic' => $values[2] ?? '',
                'destination_basis' => $values[3] ?? '', 'legal_reference' => $values[4] ?? '',
                'nfse_supported' => $values[7] === 'S'];
        }
        if (! is_array($catalog['cindop'] ?? null) || CanonicalJson::hash($catalog['cindop']) !== CanonicalJson::hash($rows)) {
            return $this->stop('MATERIALIZED_ROWS_CHANGED');
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_CINDOP126_SOURCE_POLICY_BOUND',
            'reference_hash' => self::REFERENCE_SHA256, ...$this->limits()];
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @return array<string, mixed>
     */
    public function resolve(array $catalog, string $code, string $environment, LocalDate $on, DateTimeImmutable $knownAt): array
    {
        $qualified = $this->qualify($catalog, $knownAt);
        if ($qualified['status'] !== 'RESOLVED') {
            return $qualified;
        }
        $reference = $catalog['cindop_reference126'];
        $profile = $reference['profiles'][$environment] ?? null;
        if (! is_array($profile)) {
            return $this->stop('EXPLICIT_ENVIRONMENT_REQUIRED', 'INVALID');
        }
        // The portal links different tables. Publication and observation do not
        // establish their deployment dates or supersession across environments.
        if ($environment !== 'LOCAL_SANDBOX') {
            return $this->stop('OFFICIAL_ENVIRONMENT_EFFECTIVE_DATE_UNPROVEN');
        }
        if ($on->value() < $profile['coverage_from'] || $on->value() > $profile['coverage_through']) {
            return $this->stop('OUTSIDE_LOCAL_COVERAGE');
        }
        $edition = $reference['sources'][$profile['edition']];
        if (preg_match('/\A[0-9]{6}\z/', $code) !== 1 || ! isset($edition['rows'][$code])) {
            return $this->stop('UNKNOWN_REFERENCE_CODE', 'INVALID');
        }
        $row = $edition['rows'][$code];
        if ($row['values'][7] !== 'S') {
            return $this->stop('REFERENCE_NOT_PERMITTED_FOR_NFSE', 'INVALID');
        }

        return ['status' => 'REFERENCE_ROW_AVAILABLE', 'reason' => 'RTC_CINDOP126_LOCAL_REFERENCE_AVAILABLE',
            'code' => $code, 'environment' => $environment, 'edition' => $profile['edition'],
            'operation_date' => $on->value(), 'date_scope' => $profile['date_scope'],
            'source_hash' => $edition['source']['sha256'], 'source_observed_at' => $edition['source']['observed_at'],
            'source_sheet' => $edition['source_sheet'], 'source_row' => $row['source_row'],
            'source_row_hash' => CanonicalJson::hash($row), 'document_model' => $profile['document_model'],
            'reference_hash' => self::REFERENCE_SHA256, ...$this->limits()];
    }

    /** @return array<string, bool> */
    private function limits(): array
    {
        return ['decision_authority' => false, 'tax_treatment_selected' => false, 'fiscal_destination_determined' => false,
            'official_effective_date_proven' => false, 'remote_deployment_proven' => false, 'production_ready' => false];
    }

    /** @return array<string, mixed> */
    private function stop(string $reason, string $status = 'ACTION_REQUIRED'): array
    {
        return ['status' => $status, 'reason' => 'RTC_CINDOP126_'.$reason, ...$this->limits()];
    }
}
