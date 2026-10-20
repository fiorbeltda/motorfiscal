<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use InvalidArgumentException;

/** Availability of an official table row,not authority to select tax treatment,rate or document fields. */
final readonly class CClassTribReference100
{
    public const CONTRACT = 'CCLASSTRIB-REFERENCE-LOCAL-2026.10.16-100';

    public const SOURCE_SHA256 = 'bee3e106d24c06257d31105658679e97e2544dcc3ac61467fbe0ca4f78c6e850';

    public const SOURCE_OBSERVED_AT = '2026-10-16T16:56:31.622022+00:00';

    public const ROWS_SHA256 = 'c840248705b0fe23e4ee2331459de873a4c3a777cf36a1677e651f4048399107';

    public const CST_ROWS_SHA256 = '94bd20f6efe0eaff2a48eea4d9a5b93afccd322fd5ce6387f05b93cb21bf852b';

    private const MODEL_INDICATORS = ['NFSE' => 'indNFSe', 'DERE' => 'indDERE', 'NFE' => 'indNFe', 'NFCE' => 'indNFCe',
        'CTE' => 'indCTe', 'CTEOS' => 'indCTeOS', 'BPE' => 'indBPe', 'BPETA' => 'indBPeTA', 'BPETM' => 'indBPeTM',
        'NF3E' => 'indNF3e', 'NFSEVIA' => 'indNFSe Via', 'NFCOM' => 'indNFCom', 'NFAG' => 'indNFAg',
        'NFGAS' => 'indNFGas', 'DIR' => 'indDIR', 'DUIMP' => 'indDUIMP', 'NFEABI' => 'indNFeABI'];

    /**
     * @param  array<string, mixed>  $catalog
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function resolve(array $catalog, string $code, string $model, LocalDate $on, DateTimeImmutable $knownAt, array $policy): array
    {
        $expected = ['contract' => self::CONTRACT, 'source_sha256' => self::SOURCE_SHA256,
            'source_observed_at' => self::SOURCE_OBSERVED_AT, 'rows_sha256' => self::ROWS_SHA256,
            'cst_rows_sha256' => self::CST_ROWS_SHA256, 'scope' => 'OFFICIAL_REFERENCE_ONLY_NOT_ENGINE_DECISION',
            'remote_deployment_proven' => false];
        if (CanonicalJson::hash($policy) !== CanonicalJson::hash($expected)) {
            return $this->stop('SOURCE_POLICY_NOT_BOUND');
        }
        $source = $catalog['sources']['cclasstrib'] ?? null;
        if (! is_array($source) || ($source['sha256'] ?? null) !== self::SOURCE_SHA256
            || ($source['observed_at'] ?? null) !== self::SOURCE_OBSERVED_AT
            || ($source['version'] ?? null) !== 'IT2025.002 v1.70') {
            return $this->stop('SOURCE_PROVENANCE_NOT_BOUND');
        }
        if ($knownAt < new DateTimeImmutable(self::SOURCE_OBSERVED_AT)) {
            return $this->stop('SOURCE_NOT_YET_OBSERVED');
        }
        $rows = $catalog['cclasstrib'] ?? null;
        $csts = $catalog['cst_reference_rows'] ?? null;
        if (! is_array($rows) || ! is_array($csts) || count($rows) !== 173 || count($csts) !== 18
            || ! hash_equals(self::ROWS_SHA256, CanonicalJson::hash($rows))
            || ! hash_equals(self::CST_ROWS_SHA256, CanonicalJson::hash($csts))) {
            return $this->stop('SOURCE_ROWS_CHANGED');
        }
        if (preg_match('/\A[0-9]{6}\z/', $code) !== 1 || ! isset($rows[$code])) {
            return $this->stop('UNKNOWN_REFERENCE_CODE', 'INVALID');
        }
        if (! isset(self::MODEL_INDICATORS[$model])) {
            return $this->stop('EXPLICIT_SUPPORTED_DOCUMENT_MODEL_REQUIRED', 'INVALID');
        }
        $row = $rows[$code];
        if (! is_array($row) || ! is_string($row['cst'] ?? null) || $row['cst'] !== substr($code, 0, 3)
            || ! isset($csts[$row['cst']])) {
            return $this->stop('ROW_CST_BINDING_CONFLICT', 'INVALID');
        }
        if (! $this->date($row['valid_from'] ?? null)
            || (($row['valid_to'] ?? null) !== null && (! $this->date($row['valid_to']) || $row['valid_to'] < $row['valid_from']))) {
            return $this->stop('ROW_VALIDITY_REQUIRED');
        }
        if ($on->value() < $row['valid_from'] || ($row['valid_to'] !== null && $on->value() > $row['valid_to'])) {
            return $this->stop('OUTSIDE_ROW_VALIDITY');
        }
        $indicator = self::MODEL_INDICATORS[$model];
        if (($row['document_and_group_indicators'][$indicator] ?? null) !== 1) {
            return $this->stop('REFERENCE_NOT_PERMITTED_FOR_DOCUMENT_MODEL', 'INVALID');
        }

        return ['status' => 'REFERENCE_ROW_AVAILABLE', 'reason' => 'CCLASS100_OFFICIAL_ROW_AVAILABLE_NOT_TAX_DECISION',
            'kind' => 'OFFICIAL_REFERENCE_NOT_ENGINE_CLASSIFICATION_RULE', 'decision_authority' => false,
            'code' => $code, 'cst' => $row['cst'], 'document_model' => $model, 'document_indicator' => $indicator,
            'valid_from' => $row['valid_from'], 'valid_to' => $row['valid_to'],
            'source_sha256' => self::SOURCE_SHA256, 'source_observed_at' => self::SOURCE_OBSERVED_AT,
            'source_row' => $row['source_row'], 'source_sheet' => $row['source_sheet'],
            'row_hash' => CanonicalJson::hash($row), 'tpDoacao_reference' => $row['tpDoacao'],
            'tpRBSN_reference' => $row['tpRBSN'], 'engine_decision_required' => true, ...$this->limits()];
    }

    private function date(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }
        try {
            LocalDate::fromString($value);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /** @return array<string, bool> */
    private function limits(): array
    {
        return ['tax_treatment_selected' => false, 'rate_determined' => false, 'document_indicator_emitted' => false,
            'legal_applicability_approved' => false, 'government_evidence_authenticated' => false,
            'remote_deployment_proven' => false, 'xml_generated' => false, 'posting_authorized' => false, 'production_ready' => false];
    }

    /** @return array<string, mixed> */
    private function stop(string $reason, string $status = 'ACTION_REQUIRED'): array
    {
        return ['status' => $status, 'reason' => 'CCLASS100_'.$reason, 'decision_authority' => false, ...$this->limits()];
    }
}
