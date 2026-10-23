<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use DateTimeImmutable;

/** Article12 §2 V only. Never assesses own PIS/Cofins from withholding results. */
final readonly class LegacyExclusionWindow135
{
    public const AVAILABLE_AT = '2026-10-23T10:41:42.939166+00:00';

    /** @return array<string, mixed> */
    public static function policy(): array
    {
        return ['contract' => 'LOCAL-INCIDENT-LEGACY-EXCLUSIONS-135-V1',
            'article' => 'LC214_ART12_PAR2_V',
            'url' => 'https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp214.htm',
            'source_sha256' => '70672c70d87f6e8978c34cd10056fd2d91e7a80c6f3483fa8715c850aae6d9c7',
            'observed_at' => self::AVAILABLE_AT, 'valid_from' => '2026-01-01', 'valid_to' => '2032-12-31',
            'taxes' => ['ISS', 'ICMS', 'PIS', 'COFINS'], 'scope' => 'LOCAL_OPERATION_ASSESSMENT_DECLARATIONS'];
    }

    /**
     * Exact records belong to the same document and operative day. Empty/omitted
     * taxes are missing evidence, never an implicit zero or non-incidence.
     *
     * @param  array<string, mixed>  $policy
     * @param  array<string, mixed>  $context
     * @param  array<array-key, mixed>  $assessments
     * @return array<string, mixed>
     */
    public function reconcile(array $policy, array $context, array $assessments, DateTimeImmutable $known): array
    {
        if (CanonicalJson::encode($policy) !== CanonicalJson::encode(self::policy())) {
            return $this->stop('SOURCE_POLICY_CONFLICT');
        }
        if ($known < new DateTimeImmutable(self::AVAILABLE_AT)) {
            return $this->stop('SOURCE_NOT_YET_KNOWN');
        }
        if (! $this->exactKeys($context, ['document_reference', 'supply_on', 'composed_price_brl', 'fresh_iss_binding_hash'])
            || ! is_string($context['document_reference']) || trim($context['document_reference']) === ''
            || ! is_string($context['fresh_iss_binding_hash']) || preg_match('/\A[a-f0-9]{64}\z/', $context['fresh_iss_binding_hash']) !== 1
            || ! $this->money($context['composed_price_brl']) || ! is_string($context['supply_on'])) {
            return $this->stop('EXACT_DOCUMENT_CONTEXT_REQUIRED');
        }
        $on = DateTimeImmutable::createFromFormat('!Y-m-d', $context['supply_on']);
        if ($on === false || $on->format('Y-m-d') !== $context['supply_on']) {
            return $this->stop('DOCUMENT_DATE_INVALID');
        }
        if ($context['supply_on'] < $policy['valid_from'] || $context['supply_on'] > $policy['valid_to']) {
            return $this->stop('OUTSIDE_ARTICLE12_PAR2_V_WINDOW');
        }
        if (! array_is_list($assessments) || count($assessments) !== 4) {
            return $this->stop('ALL_FOUR_OWN_TAX_ASSESSMENTS_REQUIRED');
        }
        $rows = [];
        $references = [];
        $total = DecimalValue::fromString('0.00');
        foreach ($assessments as $assessment) {
            if (! is_array($assessment) || ! $this->exactKeys($assessment,
                ['tax', 'basis', 'document_context_hash', 'amount_brl', 'evidence_reference', 'assessment_source_hash'])) {
                return $this->stop('EXACT_ASSESSMENT_RECORD_REQUIRED');
            }
            $tax = $assessment['tax'];
            if (! is_string($tax) || ! in_array($tax, $policy['taxes'], true) || isset($rows[$tax])) {
                return $this->stop('DISTINCT_ALLOWED_TAXES_REQUIRED');
            }
            if ($assessment['basis'] !== 'OWN_OPERATION_INCIDENT_AMOUNT') {
                return $this->stop('WITHHOLDING_OR_UNASSESSED_AMOUNT_NOT_OWN_INCIDENCE');
            }
            if ($assessment['document_context_hash'] !== CanonicalJson::hash($context)) {
                return $this->stop('ASSESSMENT_DOCUMENT_CONTEXT_CONFLICT');
            }
            if (! $this->money($assessment['amount_brl']) || ! is_string($assessment['evidence_reference'])
                || trim($assessment['evidence_reference']) === '' || isset($references[$assessment['evidence_reference']])
                || ! is_string($assessment['assessment_source_hash'])
                || preg_match('/\A[a-f0-9]{64}\z/', $assessment['assessment_source_hash']) !== 1) {
                return $this->stop('AMOUNT_AND_DISTINCT_ASSESSMENT_PROVENANCE_REQUIRED');
            }
            $references[$assessment['evidence_reference']] = true;
            $rows[$tax] = $assessment;
            $total = $total->plus(DecimalValue::fromString($assessment['amount_brl']));
        }
        $net = DecimalValue::fromString($context['composed_price_brl'])->minus($total);
        if ($net->isNegative()) {
            return $this->stop('INCIDENT_AMOUNTS_EXCEED_DOCUMENT_PRICE');
        }
        ksort($rows);
        $value = ['policy' => $policy, 'document_context' => $context, 'assessments' => $rows];

        return ['status' => 'ARTICLE12_PAR2_V_DECLARED_INCIDENT_AMOUNTS_RECONCILED',
            'assessments' => $rows, 'declared_legacy_exclusion_brl' => $total->value(),
            'price_after_declared_legacy_exclusions_brl' => $net->value(),
            'binding_hash' => CanonicalJson::hash($value),
            'other_article12_exclusions_assessed' => false, 'own_tax_assessments_reexecuted' => false,
            'proof_authenticated' => false, 'legacy_exclusion_legally_approved' => false,
            'document_obligation_complete' => false, 'production_ready' => false];
    }

    /** @param array<array-key, mixed> $value
     * @param  list<string>  $keys
     */
    private function exactKeys(array $value, array $keys): bool
    {
        return count($value) === count($keys) && array_diff(array_keys($value), $keys) === [];
    }

    private function money(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,14})\.[0-9]{2}\z/', $value) === 1;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'LEGACY135_'.$reason,
            'legacy_exclusion_legally_approved' => false, 'document_obligation_complete' => false, 'production_ready' => false];
    }
}
