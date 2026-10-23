<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\ISS\IssDocumentBase133;
use DateTimeImmutable;

/** Own incident line contributions, not federal withholding or monthly payable tax. */
final readonly class SoftwareOwnRevenue135
{
    public const CONTRACT = 'LOCAL-SOFTWARE-OWN-REVENUE-135-V2';

    public const AVAILABLE_AT = '2026-10-23T11:03:08.451956+00:00';

    /** @return array<string, mixed> */
    public static function policy(): array
    {
        return ['contract' => self::CONTRACT, 'available_at' => self::AVAILABLE_AT,
            'material_period' => ['from' => '2026-01-01', 'to' => '2026-12-31'],
            'pis_percent' => '0.65', 'cofins_percent' => '3.00',
            'iss_contribution_base_rule' => 'REVENUE_LESS_DOCUMENT_ISS_UNDER_LOCAL_TEMA118_CLAIM',
            'court' => ['case' => 'RE592616_TEMA118', 'merit_decided_on' => '2026-10-21', 'minutes_published_on' => '2026-10-23',
                'observed_at' => self::AVAILABLE_AT, 'source_sha256' => 'ff9ef0228ee075941384512042499eedf77db333a238c9f21e39036eac642da0',
                'final_judgment_verified' => false, 'modulation_verified' => false],
            'articles' => ['L10833_ART10_XXV_PAR2_ART15_V', 'L9715_ART2_I_ART3_ART8_I',
                'L9718_ART2_ART3_ART8', 'MP2158_ART14_III_PAR1', 'LC116_ART1_PAR2_ITEM1_01'],
            'sources' => [
                'law10833.html' => 'b8d988f077f01d698f43e137116f74b17a0d9c8e4394e3ab5d8c7c04af3b57c2',
                'law9718.html' => 'a3e44fe9a8971c402b9e5abe4bbccf15e514a09e3ff766509338f1166c8c94be',
                'law9715.html' => 'fd8362e0deee013ee899751fba10e40563db43f5f5c2cda1c82f0bacf2491955',
                'mp2158.html' => '8354d89c9b48e743d918ff6e1efdb87a588925b9cbe3e6289e094cf0976a870d',
                'lc116.html' => '07d4cca637235e539c55fae94c008ecd1d5d4b363c8e9a83531ebb0fa8e89f67'],
            'legacy_window' => LegacyExclusionWindow135::policy(),
            'rounding' => 'HALF_UP_CENTS_PER_LINE_NOT_MONTHLY_ASSESSMENT'];
    }

    /** @param array<string, mixed> $iss Replayed133/134 result from caller.
     * @param  array<array-key, mixed>  $proofs
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function calculate(array $iss, array $proofs, array $policy, DateTimeImmutable $known): array
    {
        if (CanonicalJson::encode($policy) !== CanonicalJson::encode(self::policy())) {
            return $this->stop('EXACT_SOURCE_POLICY_REQUIRED');
        }
        if ($known < new DateTimeImmutable(self::AVAILABLE_AT)) {
            return $this->stop('SOURCE_NOT_YET_KNOWN');
        }
        if (($iss['status'] ?? null) !== 'LOCAL_ISS_DOCUMENT_BASES_CALCULATED'
            || ($iss['contract'] ?? null) !== IssDocumentBase133::CONTRACT
            || ($iss['source_policy_hash'] ?? null) !== CanonicalJson::hash(IssDocumentBase133::policy())
            || ! is_array($iss['documents'] ?? null) || count($iss['documents']) !== 2
            || ! $this->hash($iss['binding_hash'] ?? null)) {
            return $this->stop('FRESH_DOCUMENT_ISS_REQUIRED');
        }
        if (! array_is_list($proofs) || count($proofs) !== 2) {
            return $this->stop('TWO_OWN_REVENUE_PROOFS_REQUIRED');
        }
        $documents = [];
        $ids = [];
        foreach ($iss['documents'] as $document) {
            if (! is_array($document) || ! is_string($document['role'] ?? null)
                || ! in_array($document['role'], ['RTC_FOREIGN', 'RTC_DOMESTIC'], true)
                || isset($documents[$document['role']]) || ($document['price_currency'] ?? null) !== 'BRL'
                || ! $this->money($document['price_for_iss_evaluation_in_input_currency'] ?? null)) {
                return $this->stop('TWO_DISTINCT_BRL_DOCUMENTS_REQUIRED');
            }
            $matches = array_values(array_filter($proofs, static fn (mixed $p): bool => is_array($p)
                && ($p['value']['document']['role'] ?? null) === $document['role']));
            if (count($matches) !== 1) {
                return $this->stop('UNIQUE_PROOF_PER_DOCUMENT_REQUIRED');
            }
            $proof = $matches[0];
            if (! $this->captured($proof, $known) || isset($ids[$proof['evidence_id']])) {
                return $this->stop('CAPTURED_DISTINCT_CURRENT_PROOFS_REQUIRED');
            }
            $ids[$proof['evidence_id']] = true;
            $value = $proof['value'];
            $context = $this->context($iss, $document);
            if (! $this->keys($value, ['source_policy_hash', 'document', 'profile', 'revenue'])
                || ($value['source_policy_hash'] ?? null) !== CanonicalJson::hash($policy)
                || CanonicalJson::encode($value['document']) !== CanonicalJson::encode($context)
                || CanonicalJson::encode($value['profile']) !== CanonicalJson::encode(self::profile())) {
                return $this->stop('DOCUMENT_AND_OWN_REGIME_PROOF_CONFLICT');
            }
            $revenue = $value['revenue'];
            if (! is_array($revenue) || ! $this->keys($revenue,
                ['recognized_on', 'competence', 'recognition_basis', 'service_revenue_brl', 'treatment', 'customer_country', 'bank_inflow'])
                || ! $this->day($revenue['recognized_on'])
                || $revenue['recognized_on'] < $policy['material_period']['from']
                || $revenue['recognized_on'] > $policy['material_period']['to']
                || $revenue['recognized_on'] > $known->format('Y-m-d')
                || $revenue['competence'] !== substr($revenue['recognized_on'], 0, 7)
                || $revenue['recognition_basis'] !== 'ACCRUAL_REVENUE_LINE'
                || $revenue['service_revenue_brl'] !== $document['price_for_iss_evaluation_in_input_currency']
                || ! is_string($revenue['customer_country']) || preg_match('/\A[A-Z]{2}\z/', $revenue['customer_country']) !== 1
                || ! $this->day($proof['valid_from']) || ! $this->day($proof['valid_to'])
                || $proof['valid_from'] > min($document['iss_supply_on'], $revenue['recognized_on'])
                || $proof['valid_to'] < max($document['iss_supply_on'], $revenue['recognized_on'])) {
                return $this->stop('DATED_REVENUE_LINE_NOT_BOUND');
            }
            $exempt = $revenue['treatment'] === 'SERVICE_EXPORT_WITH_DIVISAS';
            if ($exempt) {
                if (! $this->inflow($revenue, $context, $proof, $known)) {
                    return $this->stop('FOREIGN_CUSTOMER_AND_CAPTURED_DIVISAS_REQUIRED');
                }
            } elseif ($revenue['treatment'] !== 'ORDINARY_CUMULATIVE' || $revenue['bank_inflow'] !== null) {
                return $this->stop('EXPLICIT_OWN_CONTRIBUTION_TREATMENT_REQUIRED');
            }
            $issAmount = $document['iss_tax_brl'] ?? null;
            if ($issAmount === null && ($document['iss_treatment'] ?? null) !== 'LOCAL_EXPORT_NON_INCIDENCE_CANDIDATE') {
                return $this->stop('EXPLICIT_OWN_ISS_TREATMENT_REQUIRED');
            }
            if ($issAmount !== null && ! $this->money($issAmount)) {
                return $this->stop('EXACT_OWN_ISS_AMOUNT_REQUIRED');
            }
            $base = DecimalValue::fromString($revenue['service_revenue_brl'])->minus(DecimalValue::fromString($issAmount ?? '0.00'));
            if ($base->isNegative()) {
                return $this->stop('DOCUMENT_ISS_EXCEEDS_REVENUE');
            }
            $amounts = ['ISS' => $issAmount ?? '0.00', 'ICMS' => '0.00',
                'PIS' => $exempt ? '0.00' : $this->tax($base, $policy['pis_percent']),
                'COFINS' => $exempt ? '0.00' : $this->tax($base, $policy['cofins_percent'])];
            $legacyContext = ['document_reference' => $document['document_reference'], 'supply_on' => $document['iss_supply_on'],
                'composed_price_brl' => $revenue['service_revenue_brl'], 'fresh_iss_binding_hash' => $iss['binding_hash']];
            $rows = [];
            foreach ($amounts as $tax => $amount) {
                $rows[] = ['tax' => $tax, 'basis' => 'OWN_OPERATION_INCIDENT_AMOUNT',
                    'document_context_hash' => CanonicalJson::hash($legacyContext), 'amount_brl' => $amount,
                    'evidence_reference' => $proof['evidence_id'].'#'.$tax,
                    'assessment_source_hash' => CanonicalJson::hash(['proof' => $proof['source_hash'], 'tax' => $tax,
                        'amount' => $amount, 'policy' => $policy, 'iss' => $iss['binding_hash']])];
            }
            $window = (new LegacyExclusionWindow135)->reconcile($policy['legacy_window'], $legacyContext, $rows, $known);
            if ($window['status'] !== 'ARTICLE12_PAR2_V_DECLARED_INCIDENT_AMOUNTS_RECONCILED') {
                return [...$this->stop('LEGACY_WINDOW_RECONCILIATION_REQUIRED'), 'upstream_reason' => $window['reason']];
            }
            $documents[$document['role']] = ['role' => $document['role'], 'document_reference' => $document['document_reference'],
                'recognized_on' => $revenue['recognized_on'], 'competence' => $revenue['competence'],
                'own_contribution_treatment' => $revenue['treatment'], 'own_contribution_base_brl' => $exempt ? null : $base->value(),
                'pis_rate_percent' => $exempt ? null : $policy['pis_percent'], 'cofins_rate_percent' => $exempt ? null : $policy['cofins_percent'],
                'own_incident_amounts_brl' => $amounts, 'fresh_iss_tax_brl' => $issAmount,
                'icms_treatment' => 'PURE_LISTED_SERVICE_NOT_SUBJECT_LC116_ART1_PAR2',
                'legacy_reconciliation' => $window, 'proof_hash' => $proof['source_hash']];
        }
        ksort($documents);

        return ['status' => 'LOCAL_SOFTWARE_OWN_REVENUE_LINES_CALCULATED', 'documents' => array_values($documents),
            'binding_hash' => CanonicalJson::hash(['documents' => $documents, 'iss' => $iss['binding_hash'], 'policy' => $policy,
                'known_at' => $known->format('Y-m-d\TH:i:s.uP')]),
            'own_pis_cofins_lines_calculated' => true, 'withholding_used_as_own_incidence' => false,
            'rtc_role_used_as_contribution_treatment' => false, 'monthly_liability_calculated' => false,
            'monthly_rounding_and_residuals_reconciled' => false, 'bank_or_taxpayer_evidence_authenticated' => false,
            'judicial_temporal_effects_approved' => false, 'final_judgment_verified' => false,
            'legacy_exclusion_legally_approved' => false, 'document_obligation_complete' => false, 'production_ready' => false];
    }

    /** @return array<string, mixed> */
    public static function profile(): array
    {
        return ['supplier_status' => 'BR_PRIVATE_ORDINARY_COMPANY', 'service' => 'DOMESTICALLY_DEVELOPED_SOFTWARE_SERVICE',
            'imported_software' => false, 'mixed_goods' => false, 'other_non_cumulative_exception' => false,
            'simples_mei_cooperative_financial_or_special_regime' => false,
            'canceled_revenue' => false, 'other_revenue_base_adjustments' => [], 'other_individual_judicial_adjustments' => false,
            'iss_exclusion_basis' => 'LOCAL_TEMA118_SAME_DOCUMENT_ISS_COMPONENT',
            'ibs_cbs_in_document_service_price' => false, 'iss_in_revenue_base' => false];
    }

    /** @param array<string, mixed> $iss
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function context(array $iss, array $document): array
    {
        $national = $document['municipal_result']['national_result'] ?? [];

        return ['role' => $document['role'], 'document_reference' => $document['document_reference'],
            'contract_reference' => $document['contract_reference'], 'unit_reference' => $document['unit_reference'],
            'line_reference' => $document['line_reference'], 'iss_supply_on' => $document['iss_supply_on'],
            'supplier_id' => $national['supplier_id'] ?? null, 'customer_id' => $national['acquirer_id'] ?? null,
            'fresh_iss_binding_hash' => $iss['binding_hash'], 'composed_price_brl' => $document['price_for_iss_evaluation_in_input_currency']];
    }

    /** @param array<string, mixed> $proof */
    private function captured(array $proof, DateTimeImmutable $known): bool
    {
        if (! $this->keys($proof, ['contract', 'evidence_id', 'observed_at', 'valid_from', 'valid_to',
            'revoked_at', 'superseded_at', 'value', 'origin', 'source_hash']) || $proof['contract'] !== self::CONTRACT
            || ! is_string($proof['evidence_id']) || trim($proof['evidence_id']) === '' || ! is_array($proof['value'])) {
            return false;
        }
        $observed = $this->instant($proof['observed_at']);
        if ($observed === null || $observed > $known || $observed < new DateTimeImmutable(self::AVAILABLE_AT)) {
            return false;
        }
        foreach (['revoked_at', 'superseded_at'] as $field) {
            if ($proof[$field] !== null && ($this->instant($proof[$field]) === null || $this->instant($proof[$field]) <= $known)) {
                return false;
            }
        }
        $origin = $proof['origin'];
        $content = array_diff_key($proof, ['origin' => true, 'source_hash' => true]);

        return is_array($origin) && $this->keys($origin, ['reference', 'media_type', 'content', 'content_sha256'])
            && is_string($origin['reference']) && trim($origin['reference']) !== '' && $origin['media_type'] === 'application/json'
            && $origin['content'] === CanonicalJson::encode($content)
            && $origin['content_sha256'] === hash('sha256', $origin['content'])
            && $proof['source_hash'] === CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true]));
    }

    /** @param array<string, mixed> $revenue
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $proof
     */
    private function inflow(array $revenue, array $context, array $proof, DateTimeImmutable $known): bool
    {
        $bank = $revenue['bank_inflow'];
        if ($revenue['customer_country'] === 'BR' || ! is_array($bank)
            || ! $this->keys($bank, ['reference', 'media_type', 'content', 'content_sha256'])
            || ! is_string($bank['reference']) || trim($bank['reference']) === '' || $bank['media_type'] !== 'application/json'
            || ! is_string($bank['content']) || $bank['content_sha256'] !== hash('sha256', $bank['content'])) {
            return false;
        }
        $record = json_decode($bank['content'], true);
        if (! is_array($record) || ! $this->keys($record, ['document_reference', 'payer_id', 'payee_id', 'payer_country',
            'settled_on', 'currency', 'credited_brl', 'foreign_exchange_inflow'])) {
            return false;
        }

        return CanonicalJson::encode($record) === $bank['content'] && $record['document_reference'] === $context['document_reference']
            && is_string($context['supplier_id']) && $context['supplier_id'] !== ''
            && is_string($context['customer_id']) && $context['customer_id'] !== ''
            && $record['payer_id'] === $context['customer_id'] && $record['payee_id'] === $context['supplier_id']
            && $record['payer_country'] === $revenue['customer_country'] && $this->day($record['settled_on'])
            && $record['settled_on'] <= $known->format('Y-m-d') && $record['settled_on'] >= $revenue['recognized_on']
            && $record['settled_on'] >= $proof['valid_from'] && $record['settled_on'] <= $proof['valid_to']
            && $record['currency'] === 'BRL' && $record['credited_brl'] === $revenue['service_revenue_brl']
            && DecimalValue::fromString($record['credited_brl'])->compareTo(DecimalValue::fromString('0.00')) > 0
            && $record['foreign_exchange_inflow'] === true;
    }

    private function tax(DecimalValue $base, string $percent): string
    {
        return $base->multipliedBy(DecimalValue::fromString($percent))->dividedBy(DecimalValue::fromString('100'), 6)->rounded(2)->value();
    }

    /** @param array<array-key, mixed> $value
     * @param  list<string>  $keys
     */
    private function keys(array $value, array $keys): bool
    {
        return count($value) === count($keys) && array_diff(array_keys($value), $keys) === [];
    }

    private function money(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,14})\.[0-9]{2}\z/', $value) === 1;
    }

    private function hash(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A[a-f0-9]{64}\z/', $value) === 1;
    }

    private function day(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function instant(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value)) {
            return null;
        }
        $format = str_contains($value, '.') ? 'Y-m-d\TH:i:s.uP' : 'Y-m-d\TH:i:sP';
        $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

        return $date !== false && $date->format($format) === $value ? $date : null;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'OWN135_'.$reason,
            'own_pis_cofins_lines_calculated' => false, 'document_obligation_complete' => false, 'production_ready' => false];
    }
}
