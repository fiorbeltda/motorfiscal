<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;

/** Qualifies a homogeneous service by the statutory annex item and evidenced NBS. */
final readonly class AnnexServiceReductionQualifier
{
    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    public function resolve(FactBag $facts, LocalDate $on, DateTimeImmutable $knownAt, array $spec): array
    {
        if (($spec['strategy'] ?? null) !== 'LC214_ANNEX_II_III'
            || ! is_string($spec['source_hash'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $spec['source_hash'])
            || ! isset($spec['valid_from'], $spec['published_at']) || ! is_array($spec['annexes'] ?? null)
            || ($spec['status'] ?? 'ACTIVE') !== 'ACTIVE'
            || $on->value() < $spec['valid_from'] || (isset($spec['valid_to']) && $on->value() > $spec['valid_to'])
            || $knownAt < new DateTimeImmutable($spec['published_at'])
            || (isset($spec['revoked_at']) && $knownAt >= new DateTimeImmutable($spec['revoked_at']))
            || (isset($spec['superseded_at']) && $knownAt >= new DateTimeImmutable($spec['superseded_at']))) {
            return $this->stop('ACTION_REQUIRED', 'RTC_ANNEX_REDUCTION_RULE_NOT_AVAILABLE');
        }
        $missing = $facts->missing(['rtc.treatment_rule_id', 'rtc.treatment_evidence_id', 'rtc.annex_service_category',
            'rtc.annex_service_item', 'rtc.service_nbs_code', 'rtc.service_nbs_evidence_id',
            'rtc.annex_service_description_confirmed', 'rtc.annex_service_only_consideration', 'rtc.annex_service_scope_evidence_id']);
        if ($missing !== []) {
            return $this->stop('ACTION_REQUIRED', 'RTC_ANNEX_SERVICE_PROOF_MISSING', $missing);
        }
        $category = (string) $facts->value('rtc.annex_service_category');
        $annex = $spec['annexes'][$category] ?? null;
        $item = is_array($annex) ? ($annex['items'][(string) $facts->value('rtc.annex_service_item')] ?? null) : null;
        if (! is_array($annex) || ! is_array($item) || $facts->value('rtc.treatment_rule_id') !== ($annex['rule_id'] ?? null)) {
            return $this->stop('INVALID', 'RTC_ANNEX_SERVICE_RULE_CONFLICT');
        }
        if ($facts->value('rtc.annex_service_description_confirmed') !== true) {
            return $this->stop('INVALID', 'RTC_ANNEX_SERVICE_DESCRIPTION_CONFLICT');
        }
        if ($facts->value('rtc.annex_service_only_consideration') !== true) {
            return $this->stop('ACTION_REQUIRED', 'RTC_MIXED_SERVICES_REQUIRE_SEPARATE_TREATMENT');
        }
        $nbs = (string) $facts->value('rtc.service_nbs_code');
        if (! preg_match('/^1\.[0-9]{4}\.[0-9]{2}\.[0-9]{2}$/', $nbs)) {
            return $this->stop('INVALID', 'RTC_ANNEX_SERVICE_NBS_INVALID');
        }
        $prefix = (string) ($item['nbs'] ?? '');
        if ($prefix === '' && $category === 'EDUCATION' && ($item['special_education'] ?? false) === true) {
            $missing = $facts->missing(['rtc.special_education_target_group', 'rtc.special_education_evidence_id']);
            if ($missing !== []) {
                return $this->stop('ACTION_REQUIRED', 'RTC_SPECIAL_EDUCATION_PROOF_MISSING', $missing);
            }
            if (! in_array($facts->value('rtc.special_education_target_group'), ['DISABILITY', 'GLOBAL_DEVELOPMENT', 'HIGH_ABILITIES'], true)
                || ! str_starts_with($nbs, '1.22')) {
                return $this->stop('INVALID', 'RTC_SPECIAL_EDUCATION_CLASSIFICATION_CONFLICT');
            }
        } elseif ($prefix === '' || ! str_starts_with(str_replace('.', '', $nbs), str_replace('.', '', $prefix))) {
            return $this->stop('INVALID', 'RTC_ANNEX_SERVICE_NBS_CONFLICT');
        }
        $glosa = '0.00';
        if ($category === 'HEALTH') {
            $missing = $facts->missing(['rtc.health_medical_audit_glosa_amount']);
            if ($missing !== []) {
                return $this->stop('ACTION_REQUIRED', 'RTC_HEALTH_GLOSA_AMOUNT_REQUIRED', $missing);
            }
            $amount = DecimalValue::fromString((string) $facts->value('rtc.health_medical_audit_glosa_amount'));
            if ($amount->isNegative() || $amount->compareTo(DecimalValue::fromString((string) $facts->value('rtc.gross_amount'))) > 0) {
                return $this->stop('INVALID', 'RTC_HEALTH_GLOSA_AMOUNT_INVALID');
            }
            if ($amount->compareTo(DecimalValue::fromString('0')) > 0) {
                $missing = $facts->missing(['rtc.health_medical_audit_glosa_evidence_id', 'rtc.health_glosa_paid']);
                if ($facts->value('rtc.health_glosa_paid') === false) {
                    $missing = [...$missing, ...$facts->missing(['rtc.health_glosa_nonpayment_evidence_id'])];
                }
                if ($missing !== []) {
                    return $this->stop('ACTION_REQUIRED', 'RTC_HEALTH_GLOSA_PROOF_MISSING', $missing);
                }
                if ($facts->value('rtc.health_glosa_paid') === false) {
                    $glosa = $amount->value();
                }
            }
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_ANNEX_SERVICE_REDUCTION_ELIGIBLE', 'missing_facts' => [],
            'rule_id' => $annex['rule_id'], 'source_article' => $annex['source_article'], 'source_hash' => $spec['source_hash'],
            'rate_reduction_percent' => '60.00', 'cclasstrib' => $annex['cclasstrib'], 'cst' => '200',
            'category' => $category, 'annex_item' => $facts->value('rtc.annex_service_item'), 'nbs' => $nbs,
            'annex_description' => $item['description'], 'excluded_medical_glosa' => $glosa,
            'nbs_evidence_id' => $facts->value('rtc.service_nbs_evidence_id'),
            'scope_evidence_id' => $facts->value('rtc.annex_service_scope_evidence_id'),
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
