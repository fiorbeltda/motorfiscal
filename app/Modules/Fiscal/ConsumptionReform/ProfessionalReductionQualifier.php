<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;

/** LC 214 art. 127: professional eligibility is independent of document classification. */
final readonly class ProfessionalReductionQualifier
{
    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    public function resolve(FactBag $facts, LocalDate $on, DateTimeImmutable $knownAt, array $spec): array
    {
        if (($spec['strategy'] ?? null) !== 'LC214_ART127'
            || ! is_string($spec['rule_id'] ?? null) || ! is_array($spec['professions'] ?? null)
            || ($spec['status'] ?? 'ACTIVE') !== 'ACTIVE'
            || ! is_string($spec['source_hash'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $spec['source_hash'])
            || ! isset($spec['valid_from'], $spec['published_at'])
            || $on->value() < $spec['valid_from'] || (isset($spec['valid_to']) && $on->value() > $spec['valid_to'])
            || $knownAt < new DateTimeImmutable($spec['published_at'])
            || (isset($spec['revoked_at']) && $knownAt >= new DateTimeImmutable($spec['revoked_at']))
            || (isset($spec['superseded_at']) && $knownAt >= new DateTimeImmutable($spec['superseded_at']))) {
            return $this->stop('ACTION_REQUIRED', 'RTC_PROFESSIONAL_REDUCTION_RULE_NOT_AVAILABLE');
        }
        $missing = $facts->missing(['rtc.treatment_rule_id', 'rtc.treatment_evidence_id',
            'rtc.professional_category', 'rtc.professional_council_supervised',
            'rtc.professional_council_evidence_id', 'provider.person_type']);
        if ($missing !== []) {
            return $this->stop('ACTION_REQUIRED', 'RTC_PROFESSIONAL_REDUCTION_PROOF_MISSING', $missing);
        }
        if ($facts->value('rtc.treatment_rule_id') !== $spec['rule_id']
            || ! in_array($facts->value('rtc.professional_category'), $spec['professions'] ?? [], true)) {
            return $this->stop('INVALID', 'RTC_PROFESSIONAL_REDUCTION_RULE_CONFLICT');
        }
        if ($facts->value('rtc.professional_council_supervised') !== true) {
            return $this->stop('INVALID', 'RTC_PROFESSIONAL_REDUCTION_NOT_ELIGIBLE');
        }
        $person = $facts->value('provider.person_type');
        $physicalEducationException = $person === 'PRIVATE_LEGAL_ENTITY'
            && $facts->value('rtc.professional_category') === 'PHYSICAL_EDUCATION';
        $checks = match (true) {
            $person === 'INDIVIDUAL' => ['rtc.professional_service_linked_habilitation' => true],
            $physicalEducationException => [],
            $person === 'PRIVATE_LEGAL_ENTITY' => [
                'rtc.professional_partners_qualified_and_supervised' => true,
                'rtc.professional_has_legal_entity_partner' => false,
                'rtc.professional_holds_legal_entity_participation' => false,
                'rtc.professional_has_unrelated_activities' => false,
                'rtc.professional_services_direct_by_partners' => true,
            ],
            default => null,
        };
        if ($checks === null) {
            return $this->stop('ACTION_REQUIRED', 'RTC_PROFESSIONAL_LEGAL_FORM_REVIEW_REQUIRED', ['provider.person_type']);
        }
        $required = array_keys($checks);
        if ($required !== []) {
            $required[] = 'rtc.professional_structure_evidence_id';
        }
        $missing = $facts->missing($required);
        if ($missing !== []) {
            return $this->stop('ACTION_REQUIRED', 'RTC_PROFESSIONAL_REDUCTION_PROOF_MISSING', $missing);
        }
        foreach ($checks as $key => $expected) {
            if ($facts->value($key) !== $expected) {
                return $this->stop('INVALID', 'RTC_PROFESSIONAL_REDUCTION_NOT_ELIGIBLE');
            }
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_PROFESSIONAL_REDUCTION_ELIGIBLE', 'missing_facts' => [],
            'rule_id' => $spec['rule_id'], 'source_article' => 'LC214 art. 127; art. 348, III, a',
            'source_hash' => $spec['source_hash'], 'rate_reduction_percent' => '30.00',
            'cclasstrib' => '200052', 'cst' => '200', 'profession' => $facts->value('rtc.professional_category'),
            'physical_education_exception' => $physicalEducationException,
            'council_evidence_id' => $facts->value('rtc.professional_council_evidence_id'),
            'structure_evidence_id' => $facts->value('rtc.professional_structure_evidence_id'),
            'treatment_evidence_id' => $facts->value('rtc.treatment_evidence_id')];
    }

    /**
     * @param  list<string>  $missing
     * @return array<string, mixed>
     */
    private function stop(string $status, string $reason, array $missing = []): array
    {
        return ['status' => $status, 'reason' => $reason, 'missing_facts' => $missing];
    }
}
