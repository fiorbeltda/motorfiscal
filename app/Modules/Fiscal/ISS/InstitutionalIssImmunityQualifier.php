<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ISS;

use App\Modules\Fiscal\ConsumptionReform\InstitutionalImmunityQualifier;
use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use Throwable;

final readonly class InstitutionalIssImmunityQualifier
{
    /** @param array<string, mixed> $rule
     * @return array<string, mixed>
     */
    public function resolve(FactBag $facts, LocalDate $on, DateTimeImmutable $knownAt, array $rule): array
    {
        if (($rule['strategy'] ?? null) !== 'CF150_VI_B_C_PAR4_CTN9_14' || ($rule['status'] ?? null) !== 'ACTIVE'
            || ! isset($rule['published_at'], $rule['valid_from'], $rule['cf_source_hash'], $rule['ctn_source_hash'])
            || ! preg_match('/^[a-f0-9]{64}$/', $rule['cf_source_hash']) || ! preg_match('/^[a-f0-9]{64}$/', $rule['ctn_source_hash'])
            || $on->value() < $rule['valid_from'] || (isset($rule['valid_to']) && $on->value() > $rule['valid_to'])
            || $knownAt < new DateTimeImmutable($rule['published_at'])
            || (isset($rule['revoked_at']) && $knownAt >= new DateTimeImmutable($rule['revoked_at']))
            || (isset($rule['superseded_at']) && $knownAt >= new DateTimeImmutable($rule['superseded_at']))) {
            return $this->stop('ACTION_REQUIRED', 'ISS_INSTITUTIONAL_IMMUNITY_RULE_NOT_AVAILABLE');
        }
        if ($on->value() >= '2033-01-01') {
            return $this->stop('INVALID', 'ISS_IMMUNITY_NOT_APPLICABLE_AFTER_EXTINCTION');
        }
        if ($facts->value('rtc.tax_treatment') !== 'IMMUNE') {
            return $this->stop('INVALID', 'ISS_RTC_INSTITUTIONAL_TREATMENT_CONFLICT');
        }
        if ($facts->has('provider.person_type') && $facts->value('provider.person_type') !== 'PRIVATE_LEGAL_ENTITY') {
            return $this->stop('INVALID', 'ISS_INSTITUTIONAL_LEGAL_PERSON_CONFLICT');
        }
        $supplier = (new InstitutionalImmunityQualifier)->resolve($facts, $on, $knownAt, $rule['supplier_rule'] ?? []);
        if ($supplier['status'] !== 'RESOLVED') {
            return $supplier;
        }
        $rows = $facts->value('iss.institutional_immunity_proof');
        if ($rows === null) {
            return $this->stop('ACTION_REQUIRED', 'ISS_INSTITUTIONAL_IMMUNITY_PROOF_MISSING');
        }
        if (! is_array($rows) || ! array_is_list($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            return $this->stop('INVALID', 'ISS_INSTITUTIONAL_IMMUNITY_PROOF_INVALID');
        }
        $proof = $rows[0];
        if (($proof['supplier_id'] ?? null) !== $facts->value('rtc.supplier_id')
            || ($proof['document_id'] ?? null) !== $facts->value('rtc.document_id')) {
            return $this->stop('INVALID', 'ISS_INSTITUTIONAL_IMMUNITY_SUPPLIER_OR_DOCUMENT_CONFLICT');
        }
        foreach (['valid_from', 'valid_to', 'observed_on'] as $key) {
            if (! is_string($proof[$key] ?? null)) {
                return $this->stop('ACTION_REQUIRED', 'ISS_INSTITUTIONAL_IMMUNITY_PERIOD_MISSING');
            }
            try {
                LocalDate::fromString($proof[$key]);
            } catch (Throwable) {
                return $this->stop('INVALID', 'ISS_INSTITUTIONAL_IMMUNITY_PERIOD_INVALID');
            }
        }
        if ($proof['valid_from'] > $proof['valid_to'] || $proof['observed_on'] > $knownAt->format('Y-m-d')) {
            return $this->stop('INVALID', 'ISS_INSTITUTIONAL_IMMUNITY_PERIOD_CONFLICT');
        }
        if ($on->value() < $proof['valid_from'] || $on->value() > $proof['valid_to']) {
            return $this->stop('ACTION_REQUIRED', 'ISS_INSTITUTIONAL_IMMUNITY_PROOF_OUTSIDE_PERIOD');
        }
        foreach (['essential_scope_evidence_id', 'iss_status_evidence_id'] as $key) {
            if (! is_string($proof[$key] ?? null) || trim($proof[$key]) === '') {
                return $this->stop('ACTION_REQUIRED', 'ISS_INSTITUTIONAL_IMMUNITY_EVIDENCE_MISSING:'.$key);
            }
        }
        if (! is_bool($proof['service_related_to_essential_purposes'] ?? null)) {
            return $this->stop('ACTION_REQUIRED', 'ISS_INSTITUTIONAL_ESSENTIAL_PURPOSE_UNPROVEN');
        }
        if ($proof['service_related_to_essential_purposes'] !== true) {
            return $this->stop('INVALID', 'ISS_INSTITUTIONAL_ESSENTIAL_PURPOSE_NOT_MET');
        }

        return ['status' => 'RESOLVED', 'reason' => 'ISS_INSTITUTIONAL_IMMUNITY_ELIGIBLE', 'missing_facts' => [],
            'source_article' => 'CF150 VI b/c e §4; CTN9 IV b/c e14', 'cf_source_hash' => $rule['cf_source_hash'],
            'ctn_source_hash' => $rule['ctn_source_hash'], 'supplier_qualification' => $supplier,
            'tribISSQN' => '2', 'tpImunidade' => $supplier['cclasstrib'] === '410006' ? '2' : '3',
            'layout_version' => 'DPS_1.01', 'essential_scope_evidence_id' => $proof['essential_scope_evidence_id'],
            'iss_status_evidence_id' => $proof['iss_status_evidence_id']];
    }

    /** @return array<string, mixed> */
    private function stop(string $status, string $reason): array
    {
        return ['status' => $status, 'reason' => $reason,
            'missing_facts' => $status === 'ACTION_REQUIRED' ? ['iss.institutional_immunity_proof'] : []];
    }
}
