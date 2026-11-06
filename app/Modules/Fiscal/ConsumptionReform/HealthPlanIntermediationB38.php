<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;

/**
 * B38 (4B.4.10): intermediação de planos de assistência à saúde (LC 214/2025, art. 240), the per-document part of the
 * health-plan regime that is issued on NFS-e (official cClassTrib 011003, CST 011, indNFSe = 1).
 *
 * Literal law (Planalto compiled text observed 03/11/2026, SHA256 0a0d190b...):
 * - art. 240: IBS and CBS on the operation value at the same rate as the health plan;
 * - art. 237: that rate is nationally uniform and equals each sphere's REFERENCE rate reduced by 60%;
 * - art. 240, par. único: Simples Nacional intermediaries stay under Simples unless they opt for the regular regime.
 *
 * In 2026 the test rates are national, so reducing them by 60% equals the art. 237 rate. From 2027 the engine
 * would apply destination rates, which are not the reference rates; that route is refused until reference rates are
 * materialized, instead of approximating them.
 * The operators' own plans (arts. 234-239: cash-basis period base, CST 820 informational documents) are not settled here.
 */
final readonly class HealthPlanIntermediationB38
{
    public const KEY = 'health_plan_intermediation_b38';

    public const TREATMENT = 'SPECIFIC_REGIME';

    public const SPECIFIC_REGIME = 'HEALTH_PLAN_INTERMEDIATION';

    public const POLICY = ['version' => 'B38', 'strategy' => 'LC214_ART240_ART237', 'rule_id' => 'RTC.TREATMENT.LC214.ART240.B38-1',
        'source_file' => HospitalityRegimeB35::SOURCE_FILE, 'source_hash' => HospitalityRegimeB35::SOURCE_SHA256,
        'valid_from' => '2026-01-01', 'uniform_rate_materialized_until' => '2026-12-31', 'cst' => '011', 'cclasstrib' => '011003',
        'rate_reduction_percent' => '60.00', 'rate_basis' => 'REFERENCE_RATES_UNIFORM_NATIONAL_ART237', 'status' => 'ACTIVE'];

    /** @param array<string, mixed> $codes */
    public static function valid(array $codes, DateTimeImmutable $knownAt): bool
    {
        $spec = $codes[self::KEY] ?? null;
        if (! is_array($spec) || ! is_string($spec['published_at'] ?? null)) {
            return false;
        }
        $policy = $spec;
        unset($policy['published_at']);

        return CanonicalJson::hash($policy) === CanonicalJson::hash(self::POLICY)
            && $knownAt >= new DateTimeImmutable($spec['published_at']);
    }

    /** @param array<string, mixed> $codes */
    public static function claims(array $codes, FactBag $facts): bool
    {
        return array_key_exists(self::KEY, $codes) && $facts->value('rtc.tax_treatment') === self::TREATMENT
            && $facts->value('rtc.specific_regime') === self::SPECIFIC_REGIME;
    }

    /**
     * @param  array<string, mixed>  $codes
     * @return array<string, mixed>
     */
    public function resolve(FactBag $facts, LocalDate $on, DateTimeImmutable $knownAt, array $codes): array
    {
        if (! self::valid($codes, $knownAt) || $on->value() < self::POLICY['valid_from']) {
            return $this->stop('ACTION_REQUIRED', 'RTC_HEALTH_PLAN_INTERMEDIATION_RULE_NOT_AVAILABLE');
        }
        if ($on->value() > self::POLICY['uniform_rate_materialized_until']) {
            return $this->stop('ACTION_REQUIRED', 'RTC_UNIFORM_NATIONAL_REFERENCE_RATE_ROUTE_NOT_MATERIALIZED');
        }
        $missing = $facts->missing(['rtc.treatment_rule_id', 'rtc.treatment_evidence_id', 'rtc.health_plan_intermediation_scope_evidence_id',
            'rtc.health_plan_operator_id']);
        if ($missing !== []) {
            return $this->stop('ACTION_REQUIRED', 'RTC_HEALTH_PLAN_INTERMEDIATION_PROOF_MISSING', $missing);
        }
        if ($facts->value('rtc.treatment_rule_id') !== self::POLICY['rule_id']) {
            return $this->stop('INVALID', 'RTC_HEALTH_PLAN_INTERMEDIATION_RULE_CONFLICT');
        }
        if ($facts->value('rtc.supplier_regime') !== 'REGULAR') {
            return $this->stop('INVALID', 'RTC_HEALTH_PLAN_INTERMEDIATION_SIMPLES_STAYS_IN_SIMPLES');
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_HEALTH_PLAN_INTERMEDIATION_ELIGIBLE', 'missing_facts' => [],
            'rule_id' => self::POLICY['rule_id'], 'regime' => 'HEALTH_PLAN_INTERMEDIATION_SPECIFIC',
            'source_article' => 'LC214 art. 240 c/c art. 237', 'source_hash' => self::POLICY['source_hash'],
            'rate_reduction_percent' => '60.00', 'cclasstrib' => '011003', 'cst' => '011',
            'rate_basis' => self::POLICY['rate_basis'], 'rate_basis_note' => '2026: alíquotas de teste nacionais (iguais às de referência)',
            'health_plan_operator_id' => $facts->value('rtc.health_plan_operator_id'),
            'scope_evidence_id' => $facts->value('rtc.health_plan_intermediation_scope_evidence_id'),
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
