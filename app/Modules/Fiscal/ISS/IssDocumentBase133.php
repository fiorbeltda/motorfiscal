<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ISS;

use App\Modules\Fiscal\ConsumptionReform\PartialExportIssDatedResults106;
use App\Modules\Fiscal\ConsumptionReform\PartialExportIssPriceComposition111;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use InvalidArgumentException;

/** Calculate separate local ISS bases from fresh material results and composed document prices. */
final readonly class IssDocumentBase133
{
    public const CONTRACT = 'LOCAL-SP-ISS-DOCUMENT-BASE-133-V1';

    public const AVAILABLE_AT = '2026-10-22T18:19:22+00:00';

    /** @return array<string, mixed> */
    public static function policy(): array
    {
        return ['contract' => self::CONTRACT, 'available_at' => self::AVAILABLE_AT,
            'composition_policy' => PartialExportIssPriceComposition111::policy(),
            'reviewed_material_period' => ['from' => '2026-01-01', 'to' => '2026-10-22'],
            'municipality' => '3550308', 'service_id' => 'SOFTWARE_DEVELOPMENT',
            'lc116_subitem' => '01.01', 'ctribnac' => '010101', 'municipal_code' => '02660',
            'annex_printed_code' => '2660', 'code_effective_from' => '2018-02-13', 'rate_percent' => '2.90',
            'base_rule' => 'SERVICE_PRICE_LESS_UNCONDITIONAL_DISCOUNTS_ISS_INCLUDED',
            'technical_rounding' => 'HALF_UP_2_DECIMALS_NOT_MUNICIPAL_ROUNDING_APPROVAL',
            'sources' => [
                'sp-law13701.html' => '9966f679d5859fb868d5639bb17e85188198444063b3fb613495e6e5111d4c89',
                'sp-decree53151.html' => 'dc6899fad522c66b1e3a27d5a32701545abb6bc5516ea501f6c622cac2b1cb29',
                'sp-in8.html' => 'd7d2e3c03445849f697cac7ada79bf5ccf419bc6ab2f954d8cba1f64d972873e',
                'sp-annex1-2026.pdf' => 'a5e03509c3b61e5f583251a335013695b15f86830950a647ccf9aae9fa4478de',
                'sp-law18066.html' => '501bfd9d83e491fdcf731938256afb7c2b91e1972d55388d08c609bf78b3887a'],
            'source_articles' => ['SP_L13701_ART14_CAPUT_PAR4', 'SP_L13701_ART16_III', 'SP_D53151_ART17', 'SP_IN8_ANNEX1_2660_NOTE23']];
    }

    /** @param array<string, mixed> $input111
     * @param  array<int|string, mixed>  $proofs
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function calculate(array $input111, array $proofs, string $knowledgeAt, array $policy): array
    {
        if (CanonicalJson::hash($policy) !== CanonicalJson::hash(self::policy())) {
            return $this->stop('EXACT_SOURCE_POLICY_REQUIRED');
        }
        $known = $this->instant($knowledgeAt);
        if ($known === null || $known < new DateTimeImmutable(self::AVAILABLE_AT)) {
            return $this->stop('POLICY_NOT_KNOWN');
        }
        foreach (['input110', 'records', 'proof'] as $key) {
            if (! is_array($input111[$key] ?? null)) {
                return $this->stop('FRESH_COMPOSITION_INPUT_REQUIRED');
            }
        }
        $input = $input111['input110'];
        foreach (['facts', 'material_records', 'result_records', 'identity_records', 'price_records', 'proof'] as $key) {
            if (! is_array($input[$key] ?? null)) {
                return $this->stop('FRESH_COMPOSITION_INPUT_REQUIRED');
            }
        }
        $prices = (new PartialExportIssPriceComposition111)->qualify($input, $input111['records'],
            $input111['proof'], $knowledgeAt, $policy['composition_policy']);
        if (($prices['document_prices_locally_composed'] ?? false) !== true) {
            return [...$this->stop('FRESH_COMPOSITION_REQUIRED'), 'upstream_reason' => $prices['reason']];
        }
        $datedPolicy = $policy['composition_policy']['price_policy']['dated_identity_policy']['dated_results_policy'];
        $dated = (new PartialExportIssDatedResults106)->qualify($input['facts'], $input['material_records'],
            $input['result_records'], $knowledgeAt, $datedPolicy);
        if (($dated['dated_material_and_results_locally_bound'] ?? false) !== true) {
            return $this->stop('FRESH_MATERIAL_RESULTS_REQUIRED');
        }
        if (! array_is_list($proofs) || count($proofs) !== 2) {
            return $this->stop('TWO_DOCUMENT_TAX_CONTEXT_PROOFS_REQUIRED');
        }
        $datedByRole = array_column($dated['documents'], null, 'role');
        $records = array_column($input['result_records'], null, 'role');
        $byRole = [];
        foreach ($proofs as $proof) {
            if (! is_array($proof)) {
                return $this->stop('TWO_DOCUMENT_TAX_CONTEXT_PROOFS_REQUIRED');
            }
            $role = $proof['value']['role'] ?? null;
            if (! is_string($role) || ! isset($datedByRole[$role]) || isset($byRole[$role])) {
                return $this->stop('DISTINCT_CURRENT_DOCUMENT_PROOFS_REQUIRED');
            }
            $byRole[$role] = $proof;
        }
        $documents = [];
        $ids = [];
        $upstreamIds = $this->evidenceIds($input111);
        foreach ($prices['documents'] as $price) {
            $role = $price['role'];
            $day = $price['iss_supply_on'];
            if ($day < $policy['reviewed_material_period']['from'] || $day > $policy['reviewed_material_period']['to']) {
                return $this->stop('MATERIAL_PERIOD_NOT_REVIEWED');
            }
            $proof = $byRole[$role];
            $value = $proof['value'];
            $expected = ['role' => $role, 'document_reference' => $price['document_reference'],
                'contract_reference' => $price['contract_reference'], 'unit_reference' => $price['unit_reference'],
                'line_reference' => $price['line_reference'], 'iss_supply_on' => $day,
                'supplier_id' => $input['facts']['rtc.supplier_id'], 'acquirer_id' => $records[$role]['iss_context']['acquirer_id'],
                'municipality' => $policy['municipality'], 'service_id' => $policy['service_id'],
                'lc116_subitem' => $policy['lc116_subitem'], 'ctribnac' => $policy['ctribnac'],
                'municipal_code' => $policy['municipal_code'], 'supplier_person_type' => 'PRIVATE_LEGAL_ENTITY',
                'supplier_iss_regime' => 'GENERAL_AD_VALOREM', 'special_base_rule' => false,
                'iss_included_in_price' => true, 'ordinary_taxation_without_benefit' => true,
                'price111_binding_hash' => $prices['binding_hash'], 'dated106_binding_hash' => $dated['binding_hash'],
                'source_policy_hash' => CanonicalJson::hash($policy)];
            if (CanonicalJson::encode($value) !== CanonicalJson::encode($expected)) {
                return [...$this->stop('GRANULAR_CLASSIFICATION_OR_TAX_CONTEXT_CONFLICT'), 'role' => $role];
            }
            $error = $this->proofError($proof, $known, $day, new DateTimeImmutable($input111['proof']['observed_at']));
            if ($error !== null) {
                return [...$this->stop($error), 'role' => $role];
            }
            if (in_array($proof['evidence_id'], $ids, true)
                || in_array($proof['evidence_id'], $upstreamIds, true)) {
                return $this->stop('DISTINCT_TAX_CONTEXT_EVIDENCE_REQUIRED');
            }
            $ids[] = $proof['evidence_id'];
            $municipal = $datedByRole[$role]['municipal_result'];
            if (($municipal['municipal_documentary_conditions_locally_bound'] ?? false) !== true) {
                return $this->stop('MUNICIPAL_RESULT_REQUIRED');
            }
            $nonIncidence = $municipal['export_non_incidence_candidate'];
            $base = null;
            $tax = null;
            if (! $nonIncidence) {
                if ($price['price_currency'] !== 'BRL') {
                    return [...$this->stop('OWN_ISS_FX_CONTRACT_REQUIRED'), 'role' => $role];
                }
                $base = $price['price_for_iss_evaluation_in_input_currency'];
                $tax = DecimalValue::fromString($base)->multipliedBy(DecimalValue::fromString($policy['rate_percent']))
                    ->dividedBy(DecimalValue::fromString('100'), 6)->rounded(2)->value();
            }
            $documents[] = [...$price, 'iss_treatment' => $nonIncidence ? 'LOCAL_EXPORT_NON_INCIDENCE_CANDIDATE' : 'LOCAL_ORDINARY_TAXABLE',
                'iss_base_brl' => $base, 'iss_tax_brl' => $tax, 'iss_rate_percent' => $nonIncidence ? null : $policy['rate_percent'],
                'iss_base_calculated' => ! $nonIncidence,
                'municipal_code' => $policy['municipal_code'], 'tax_context_proof_hash' => $proof['source_hash'],
                'material_result_hash' => CanonicalJson::hash($datedByRole[$role]),
                'municipal_result' => $municipal];
        }

        return ['status' => 'LOCAL_ISS_DOCUMENT_BASES_CALCULATED', 'contract' => self::CONTRACT,
            'documents' => $documents, 'price111_binding_hash' => $prices['binding_hash'], 'dated106_binding_hash' => $dated['binding_hash'],
            'binding_hash' => CanonicalJson::hash(['documents' => $documents, 'knowledge_at' => $knowledgeAt, 'policy' => $policy]),
            'source_policy_hash' => CanonicalJson::hash($policy),
            'iss_base_calculated' => count(array_filter($documents, static fn (array $document): bool => $document['iss_base_calculated'])) > 0,
            'rtc_allocation_used_as_iss_base' => false, 'conditional_discount_subtracted' => false,
            'documentary_currency_conversion_qualified' => false, 'legal_applicability_approved' => false,
            'municipal_rounding_approved' => false, 'government_evidence_authenticated' => false,
            'document_obligation_complete' => false, 'production_ready' => false];
    }

    /** @param array<mixed> $value
     * @return list<string>
     */
    private function evidenceIds(array $value): array
    {
        $ids = [];
        foreach ($value as $key => $item) {
            if ($key === 'evidence_id' && is_string($item)) {
                $ids[] = $item;
            } elseif (is_array($item)) {
                $ids = array_merge($ids, $this->evidenceIds($item));
            }
        }

        return array_values(array_unique($ids));
    }

    /** @param array<string, mixed> $proof */
    private function proofError(array $proof, DateTimeImmutable $known, string $day, DateTimeImmutable $priceObserved): ?string
    {
        $keys = ['contract', 'kind', 'evidence_id', 'observed_at', 'valid_from', 'valid_to', 'revoked_at',
            'superseded_at', 'value', 'origin', 'source_hash'];
        if (count($proof) !== count($keys) || array_diff(array_keys($proof), $keys) !== []
            || $proof['contract'] !== self::CONTRACT || $proof['kind'] !== 'ISS_DOCUMENT_TAX_CONTEXT'
            || ! is_string($proof['evidence_id']) || trim($proof['evidence_id']) === '') {
            return 'EXACT_TAX_CONTEXT_EVIDENCE_REQUIRED';
        }
        $observed = $this->instant($proof['observed_at']);
        if ($observed === null || $observed > $known || $observed < new DateTimeImmutable(self::AVAILABLE_AT)) {
            return 'TAX_CONTEXT_NOT_KNOWN';
        }
        if ($observed < $priceObserved) {
            return 'TAX_CONTEXT_PRECEDES_PRICE_EVIDENCE';
        }
        try {
            if (! is_string($proof['valid_from']) || ! is_string($proof['valid_to'])) {
                return 'EXACT_TAX_CONTEXT_COVERAGE_REQUIRED';
            }
            LocalDate::fromString($proof['valid_from']);
            LocalDate::fromString($proof['valid_to']);
        } catch (InvalidArgumentException) {
            return 'EXACT_TAX_CONTEXT_COVERAGE_REQUIRED';
        }
        if ($proof['valid_from'] > $day || $proof['valid_to'] < $day) {
            return 'TAX_CONTEXT_DOES_NOT_COVER_ISS_DATE';
        }
        foreach (['revoked_at', 'superseded_at'] as $key) {
            if ($proof[$key] !== null) {
                $event = $this->instant($proof[$key]);
                if ($event === null || $event <= $known) {
                    return 'TAX_CONTEXT_REVOKED_OR_SUPERSEDED';
                }
            }
        }
        $origin = $proof['origin'];
        $body = array_diff_key($proof, ['origin' => true, 'source_hash' => true]);
        if (! is_array($origin) || count($origin) !== 4
            || ! is_string($origin['reference'] ?? null) || trim($origin['reference']) === ''
            || ($origin['media_type'] ?? null) !== 'application/json'
            || ($origin['content'] ?? null) !== CanonicalJson::encode($body)
            || ($origin['content_sha256'] ?? null) !== hash('sha256', $origin['content'])) {
            return 'CAPTURED_TAX_CONTEXT_CONTENT_CONFLICT';
        }
        if (($proof['source_hash'] ?? null) !== CanonicalJson::hash(array_diff_key($proof, ['source_hash' => true]))) {
            return 'TAX_CONTEXT_ENVELOPE_HASH_CONFLICT';
        }

        return null;
    }

    private function instant(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{6})?([+-])(\d{2}):(\d{2})\z/', $value, $parts) !== 1
            || (int) $parts[2] > 14 || (int) $parts[3] > 59 || ((int) $parts[2] === 14 && $parts[3] !== '00')) {
            return null;
        }
        $format = str_contains($value, '.') ? 'Y-m-d\TH:i:s.uP' : 'Y-m-d\TH:i:sP';
        $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

        return $date !== false && $date->format($format) === $value ? $date : null;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'ISS133_'.$reason,
            'iss_base_calculated' => false, 'production_ready' => false, 'document_obligation_complete' => false];
    }
}
