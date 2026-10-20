<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use InvalidArgumentException;

/** Compares declared document memory; never calculates a tax, qualifies evidence or authorizes a document. */
final readonly class DocumentBaseReconciliation
{
    public const CONTRACT = 'NT009-LOCAL-DOCUMENT-BASE-2026.10.16-94';

    public const ANNEX_SHA256 = '103a150dd6f56ec8edcaa3a453b59a1b0e096d7cd5025d7bd06a387f141ddf39';

    public const NT_SHA256 = 'cc86c9402e317e84eb3a9f48bbd5e0b8e3dcd84f2645700190c84d45bf1ea58e';

    public const OBSERVED_AT = '2026-10-16T13:53:00.084517+00:00';

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function compare(array $document, DateTimeImmutable $knownAt, array $policy): array
    {
        if (($policy['contract'] ?? null) !== self::CONTRACT
            || ($policy['annex_sha256'] ?? null) !== self::ANNEX_SHA256
            || ($policy['nt_sha256'] ?? null) !== self::NT_SHA256
            || ($policy['observed_at'] ?? null) !== self::OBSERVED_AT) {
            return $this->stop('SOURCE_CONTRACT_NOT_BOUND');
        }
        if ($knownAt < new DateTimeImmutable(self::OBSERVED_AT)) {
            return $this->stop('SOURCE_NOT_YET_OBSERVED');
        }
        $keys = ['document_id', 'operation_id', 'evidence_id', 'competence_date', 'tpEmit', 'tribISSQN',
            'vServ', 'descIncond', 'vCalcAjusteBCIBSCBS', 'vCalcAjusteBCLocImoveis',
            'vISSQN', 'vPIS', 'vCOFINS', 'vAjusteBCIBSCBSComExt', 'expected_rtc_base_brl'];
        $missing = array_values(array_filter($keys, static fn (string $key): bool => ! array_key_exists($key, $document)));
        if ($missing !== []) {
            return [...$this->stop('EXPLICIT_DOCUMENT_MEMORY_REQUIRED'), 'missing_fields' => $missing];
        }
        if (array_diff(array_keys($document), $keys) !== []) {
            return $this->stop('ADDITIONAL_DOCUMENT_MEMORY_NOT_MAPPED');
        }
        foreach (['document_id', 'operation_id', 'evidence_id'] as $key) {
            if (! is_string($document[$key]) || trim($document[$key]) === '') {
                return $this->stop('DOCUMENT_OPERATION_EVIDENCE_IDS_REQUIRED');
            }
        }
        if (! is_string($document['competence_date'])) {
            return $this->stop('EXACT_COMPETENCE_DATE_REQUIRED');
        }
        try {
            $on = LocalDate::fromString($document['competence_date'])->value();
        } catch (InvalidArgumentException) {
            return $this->stop('EXACT_COMPETENCE_DATE_REQUIRED');
        }
        if ($on < '2026-01-01' || $on > '2032-12-31') {
            return $this->stop('SOURCE_FORMULA_PERIOD_NOT_COVERED');
        }
        if ($on > $knownAt->format('Y-m-d')) {
            return $this->stop('DOCUMENT_AFTER_KNOWLEDGE');
        }
        if (! in_array($document['tpEmit'], ['1', '2', '3'], true)
            || ! in_array($document['tribISSQN'], ['1', '2', '3', '4'], true)) {
            return $this->stop('ISS_DOCUMENT_DOMAINS_NOT_BOUND');
        }
        $money = [];
        $optional = ['vCalcAjusteBCIBSCBS', 'vCalcAjusteBCLocImoveis', 'vAjusteBCIBSCBSComExt'];
        foreach (['vServ', 'descIncond', ...$optional, 'vISSQN', 'vPIS', 'vCOFINS', 'expected_rtc_base_brl'] as $key) {
            $value = $document[$key];
            if ($value === null && in_array($key, $optional, true)) {
                $money[$key] = DecimalValue::fromString('0.00');

                continue;
            }
            $pattern = $key === 'vAjusteBCIBSCBSComExt' ? '/\A-?(?:0|[1-9][0-9]{0,14})\.[0-9]{2}\z/D'
                : '/\A(?:0|[1-9][0-9]{0,14})\.[0-9]{2}\z/D';
            if (! is_string($value) || preg_match($pattern, $value) !== 1 || $value === '-0.00') {
                return [...$this->stop('CANONICAL_DECIMAL_MEMORY_REQUIRED'), 'invalid_field' => $key];
            }
            $money[$key] = DecimalValue::fromString($value);
        }
        if ($document['vCalcAjusteBCIBSCBS'] !== null && $document['vCalcAjusteBCLocImoveis'] !== null) {
            return $this->stop('ALTERNATIVE_BASE_ADJUSTMENTS_CONFLICT');
        }
        // RN DPS_NFS-e D601 forbids presence, even if a supplied amount is zero.
        if ($document['vAjusteBCIBSCBSComExt'] !== null && $document['tpEmit'] === '1' && $document['tribISSQN'] !== '3') {
            return $this->stop('COMEXT_ADJUSTMENT_PRESENCE_FORBIDDEN_BY_D601');
        }
        $base = $money['vServ']->minus($money['descIncond'])
            ->minus($money['vCalcAjusteBCIBSCBS'])->minus($money['vCalcAjusteBCLocImoveis'])
            ->minus($money['vISSQN'])->plus($money['vAjusteBCIBSCBSComExt']);
        $legacyContributions = $on <= '2026-12-31';
        if ($legacyContributions) {
            $base = $base->minus($money['vPIS'])->minus($money['vCOFINS']);
        }
        if ($base->isNegative() || $base->compareTo(DecimalValue::fromString('999999999999999.99')) > 0) {
            return $this->stop('RESULTING_BASE_OUTSIDE_SOURCE_MONEY_DOMAIN');
        }
        $difference = $money['expected_rtc_base_brl']->minus($base)->rounded(2)->value();
        $matches = $base->compareTo($money['expected_rtc_base_brl']) === 0;

        return ['status' => $matches ? 'LOCAL_DOCUMENT_BASE_MATCH' : 'ACTION_REQUIRED',
            'reason' => $matches ? 'RTC_DOCUMENT_BASE_94_MEMORY_RECONCILED' : 'RTC_DOCUMENT_BASE_94_RTC_DOCUMENT_BASE_MISMATCH',
            'scope' => 'LOCAL_DECLARED_BASE_MEMORY_COMPARISON_ONLY',
            'document_id' => $document['document_id'], 'operation_id' => $document['operation_id'],
            'evidence_id' => $document['evidence_id'], 'competence_date' => $on,
            'calculated_document_base_brl' => $base->rounded(2)->value(),
            'expected_rtc_base_brl' => $document['expected_rtc_base_brl'], 'difference_brl' => $difference,
            'pis_cofins_subtracted' => $legacyContributions,
            'comext_adjustment_omitted' => $document['vAjusteBCIBSCBSComExt'] === null,
            'input_memory_hash' => CanonicalJson::hash($document), 'policy_hash' => CanonicalJson::hash($policy),
            'source_locators' => ['NT009 v1.01 pp17-18', 'LEIAUTE DPS_NFS-e - RT!H53', 'RN DPS_NFS-e!D601'],
            ...$this->limits()];
    }

    /** @return array<string, bool> */
    private function limits(): array
    {
        return ['inputs_materially_qualified' => false, 'source_effective_date_proven' => false,
            'tax_recalculated' => false, 'issuer_fields_generated' => false, 'xml_schema_validated' => false,
            'document_obligation_complete' => false, 'posting_authorized' => false, 'production_ready' => false];
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_DOCUMENT_BASE_94_'.$reason, ...$this->limits()];
    }
}
