<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use Throwable;

/** Supplier immunity is never inferred from the acquirer's institutional status. */
final readonly class InstitutionalImmunityQualifier
{
    /** @param array<string, mixed> $rule
     * @return array<string, mixed>
     */
    public function resolve(FactBag $facts, LocalDate $on, DateTimeImmutable $knownAt, array $rule): array
    {
        if (($rule['strategy'] ?? null) !== 'LC214_ART9_II_III_CTN14' || ($rule['status'] ?? null) !== 'ACTIVE'
            || ! isset($rule['valid_from'], $rule['published_at'], $rule['source_hash'], $rule['ctn_source_hash'])
            || ! preg_match('/^[a-f0-9]{64}$/', $rule['source_hash']) || ! preg_match('/^[a-f0-9]{64}$/', $rule['ctn_source_hash'])
            || $on->value() < $rule['valid_from'] || (isset($rule['valid_to']) && $on->value() > $rule['valid_to'])
            || $knownAt < new DateTimeImmutable($rule['published_at'])
            || (isset($rule['revoked_at']) && $knownAt >= new DateTimeImmutable($rule['revoked_at']))
            || (isset($rule['superseded_at']) && $knownAt >= new DateTimeImmutable($rule['superseded_at']))) {
            return $this->stop('ACTION_REQUIRED', 'RTC_INSTITUTIONAL_IMMUNITY_RULE_NOT_AVAILABLE');
        }
        $rows = $facts->value('rtc.institutional_immunity_proof');
        if ($rows === null) {
            return $this->stop('ACTION_REQUIRED', 'RTC_INSTITUTIONAL_IMMUNITY_PROOF_MISSING');
        }
        if (! is_array($rows) || ! array_is_list($rows) || count($rows) !== 1 || ! is_array($rows[0])) {
            return $this->stop('INVALID', 'RTC_INSTITUTIONAL_IMMUNITY_PROOF_INVALID');
        }
        $proof = $rows[0];
        if (($proof['supplier_id'] ?? null) !== $facts->value('rtc.supplier_id')
            || ($proof['document_id'] ?? null) !== $facts->value('rtc.document_id')) {
            return $this->stop('INVALID', 'RTC_INSTITUTIONAL_IMMUNITY_SUPPLIER_OR_DOCUMENT_CONFLICT');
        }
        foreach (['valid_from', 'valid_to', 'observed_on'] as $key) {
            if (! is_string($proof[$key] ?? null)) {
                return $this->stop('ACTION_REQUIRED', 'RTC_INSTITUTIONAL_IMMUNITY_PERIOD_PROOF_MISSING');
            }
            try {
                LocalDate::fromString($proof[$key]);
            } catch (Throwable) {
                return $this->stop('INVALID', 'RTC_INSTITUTIONAL_IMMUNITY_PERIOD_INVALID');
            }
        }
        if ($proof['valid_from'] > $proof['valid_to'] || $proof['observed_on'] > $knownAt->format('Y-m-d')
            || $proof['observed_on'] > $on->value()) {
            return $this->stop('INVALID', 'RTC_INSTITUTIONAL_IMMUNITY_PERIOD_CONFLICT');
        }
        if ($on->value() < $proof['valid_from'] || $on->value() > $proof['valid_to']) {
            return $this->stop('ACTION_REQUIRED', 'RTC_INSTITUTIONAL_IMMUNITY_PROOF_OUTSIDE_PERIOD');
        }
        $category = $proof['category'] ?? null;
        $religious = in_array($category, ['RELIGIOUS_ENTITY', 'RELIGIOUS_ASSISTANCE'], true);
        if (! $religious && ! in_array($category, ['POLITICAL_PARTY', 'PARTY_INSTITUTE', 'PARTY_FOUNDATION',
            'WORKERS_UNION', 'NONPROFIT_EDUCATION', 'NONPROFIT_SOCIAL_ASSISTANCE'], true)) {
            return $this->stop('INVALID', 'RTC_INSTITUTIONAL_IMMUNITY_CATEGORY_NOT_ELIGIBLE');
        }
        if ($facts->value('rtc.treatment_rule_id') !== ($religious ? 'RTC.IMMUNITY.LC214.ART9.II-1' : 'RTC.IMMUNITY.LC214.ART9.III-1')) {
            return $this->stop('INVALID', 'RTC_INSTITUTIONAL_IMMUNITY_RULE_CONFLICT');
        }
        $conditions = ['private_legal_entity', 'nonprofit'];
        $evidence = ['legal_entity_evidence_id', 'statute_evidence_id', 'nonprofit_evidence_id', 'operation_scope_evidence_id'];
        if ($religious) {
            if ($category === 'RELIGIOUS_ENTITY') {
                $conditions[] = 'professes_faith_and_practices_religion';
            } else {
                $conditions = [...$conditions, 'linked_and_maintained_by_religious_entity', 'social_assistance_supply',
                    'no_beneficiary_discrimination', 'no_beneficiary_requirements'];
                $evidence[] = 'religious_link_evidence_id';
                $evidence[] = 'beneficiary_policy_evidence_id';
            }
        } else {
            $conditions = [...$conditions, 'no_asset_or_income_distribution', 'resources_applied_in_brazil',
                'formal_accurate_bookkeeping', 'service_directly_related_to_statutory_objectives', 'benefit_not_suspended'];
            $evidence = [...$evidence, 'no_distribution_evidence_id', 'resources_application_evidence_id',
                'bookkeeping_evidence_id', 'benefit_status_evidence_id'];
            if (in_array($category, ['PARTY_INSTITUTE', 'PARTY_FOUNDATION'], true)) {
                $conditions[] = 'linked_to_political_party';
                $evidence[] = 'party_link_evidence_id';
            }
        }
        foreach ($evidence as $key) {
            if (! is_string($proof[$key] ?? null) || trim($proof[$key]) === '') {
                return $this->stop('ACTION_REQUIRED', 'RTC_INSTITUTIONAL_IMMUNITY_EVIDENCE_MISSING:'.$key);
            }
        }
        if (! is_string($facts->value('rtc.treatment_evidence_id')) || trim($facts->value('rtc.treatment_evidence_id')) === '') {
            return $this->stop('ACTION_REQUIRED', 'RTC_INSTITUTIONAL_IMMUNITY_TREATMENT_PROOF_MISSING');
        }
        foreach ($conditions as $key) {
            if (! is_bool($proof[$key] ?? null)) {
                return $this->stop('ACTION_REQUIRED', 'RTC_INSTITUTIONAL_IMMUNITY_CONDITION_UNPROVEN:'.$key);
            }
            if ($proof[$key] !== true) {
                return $this->stop('INVALID', 'RTC_INSTITUTIONAL_IMMUNITY_CONDITION_NOT_MET:'.$key);
            }
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_INSTITUTIONAL_IMMUNITY_ELIGIBLE', 'missing_facts' => [],
            'rule_id' => $facts->value('rtc.treatment_rule_id'), 'category' => $category, 'cst' => '410',
            'cclasstrib' => $religious ? '410006' : '410007', 'source_id' => $rule['source_id'],
            'source_article' => $religious ? 'LC214 art.9, II e §§2 e 4' : 'LC214 art.9, III e §§3 e 4; CTN art.14',
            'source_hash' => $rule['source_hash'], 'ctn_source_hash' => $religious ? null : $rule['ctn_source_hash'],
            'supplier_id' => $proof['supplier_id'], 'document_id' => $proof['document_id'],
            'valid_from' => $proof['valid_from'], 'valid_to' => $proof['valid_to'],
            'evidence' => array_intersect_key($proof, array_flip($evidence)),
            'previous_supplier_credits' => 'PERIOD_PROPORTIONAL_ANNULMENT_ART51',
            'acquisitions_of_institution' => 'NOT_IMMUNE_BY_INSTITUTIONAL_STATUS_ART9_PAR4'];
    }

    /** @return array<string, mixed> */
    private function stop(string $status, string $reason): array
    {
        return ['status' => $status, 'reason' => $reason,
            'missing_facts' => $status === 'ACTION_REQUIRED' ? ['rtc.institutional_immunity_proof'] : []];
    }
}
