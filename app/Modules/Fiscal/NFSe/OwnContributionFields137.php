<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\ConsumptionReform\SoftwareOwnRevenue135;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DateTimeImmutable;

/** Own line amounts and independently captured withholding status, before XML assembly. */
final readonly class OwnContributionFields137
{
    public const CONTRACT = 'LOCAL-OWN-CONTRIBUTION-FIELDS-137-V2';

    public const AVAILABLE_AT = '2026-10-23T18:02:44+00:00';

    /** @return array<string, mixed> */
    /**
     * @return array<string, mixed>
     */
    public static function policy(): array
    {
        return ['contract' => self::CONTRACT, 'available_at' => self::AVAILABLE_AT,
            'own_revenue_policy' => SoftwareOwnRevenue135::policy(),
            'layout_sha256' => '103a150dd6f56ec8edcaa3a453b59a1b0e096d7cd5025d7bd06a387f141ddf39',
            'table_sources' => [
                'tabela-4-3-3.doc' => '9fc93da5feca352592b4829ef1a586ae23baad4f441f7d3d1be711de7712a61b',
                'tabela-4-3-4.doc' => 'e360d38bec4f985447563cb02fd3f96ce86a8fa03a428c0d57f94b17f7f790f8',
                'tabela-4-3-14.doc' => '62b72ce9f3b90fd61bee735a3210c1920b6c21ae36f61159f906cf8bef4b4192',
                'tabela-4-3-15.doc' => 'a7658d9b4e3c10b864577e00f4933a919621bdc43cae6cafcaded9c08354f024'],
            'cst_by_own_treatment' => ['ORDINARY_CUMULATIVE' => '01', 'SERVICE_EXPORT_WITH_DIVISAS' => '08'],
            'export_reference' => 'RFB_TABLE_4_3_15_CODE_402',
            'withholding_scope' => 'BOUND_FRESH_PAYMENT_EVALUATION_IN_SEPARATE_COORDINATOR',
            'non_incidence_base_and_rates' => 'OMITTED_NOT_NUMERIC_ZERO',
            'withholding_claim_in_own_declaration' => 'FORBIDDEN',
            'local_scope' => 'OWN_SOFTWARE_LINE_FIELDS_NOT_MONTHLY_TAX_OR_PRODUCTION_APPROVAL'];
    }

    /** @param array<string, mixed> $iss
     * @param  array<array-key, mixed>  $ownProofs
     * @param  array<array-key, mixed>  $proofs
     * @param  array<string, mixed>  $policy
     * @return array<string, mixed>
     */
    public function qualify(array $iss, array $ownProofs, array $proofs, array $policy, DateTimeImmutable $known, string $tableRoot, string $fiscalRoot): array
    {
        if (CanonicalJson::encode($policy) !== CanonicalJson::encode(self::policy())) {
            return $this->stop('EXACT_POLICY_REQUIRED');
        }
        if ($known < new DateTimeImmutable(self::AVAILABLE_AT)) {
            return $this->stop('SOURCE_NOT_YET_KNOWN');
        }
        foreach ($policy['table_sources'] as $name => $hash) {
            if (! is_file($tableRoot.'/'.$name) || hash_file('sha256', $tableRoot.'/'.$name) !== $hash) {
                return $this->stop('ORIGINAL_CST_SOURCE_BYTES_REQUIRED');
            }
        }
        foreach (PartialExportDpsPair135::additionalSources() as $name => $hash) {
            if (! is_file($fiscalRoot.'/sources/official/'.$name) || hash_file('sha256', $fiscalRoot.'/sources/official/'.$name) !== $hash) {
                return $this->stop('ORIGINAL_OWN_AND_LAYOUT_SOURCE_BYTES_REQUIRED');
            }
        }
        $own = (new SoftwareOwnRevenue135)->calculate($iss, $ownProofs, $policy['own_revenue_policy'], $known);
        if ($own['status'] !== 'LOCAL_SOFTWARE_OWN_REVENUE_LINES_CALCULATED') {
            return [...$this->stop('FRESH_OWN_CALCULATION_REQUIRED'), 'upstream_reason' => $own['reason']];
        }
        if (! array_is_list($proofs) || count($proofs) !== 2) {
            return $this->stop('TWO_CAPTURED_DOCUMENT_DECLARATIONS_REQUIRED');
        }
        $ids = [];
        foreach ($ownProofs as $prior) {
            $ids[$prior['evidence_id']] = true;
        }
        $documents = [];
        foreach ($own['documents'] as $line) {
            $role = $line['role'];
            $matched = array_values(array_filter($proofs, static fn (mixed $p): bool => is_array($p) && ($p['value']['document']['role'] ?? null) === $role));
            if (count($matched) !== 1) {
                return $this->stop('UNIQUE_DECLARATION_PER_DOCUMENT_REQUIRED');
            }
            $proof = $matched[0];
            if (! $this->captured($proof, $known) || isset($ids[$proof['evidence_id']])) {
                return $this->stop('CURRENT_DISTINCT_CAPTURED_DECLARATIONS_REQUIRED');
            }
            $ids[$proof['evidence_id']] = true;
            $upstream = array_values(array_filter($ownProofs, static fn (array $p): bool => $p['source_hash'] === $line['proof_hash']));
            if (count($upstream) !== 1 || new DateTimeImmutable($proof['observed_at']) < new DateTimeImmutable($upstream[0]['observed_at'])) {
                return $this->stop('DECLARATION_PREDATES_OWN_ASSESSMENT');
            }
            $value = $proof['value'];
            if (! $this->keys($value, ['source_policy_hash', 'document', 'own_revenue_result_hash', 'own_proof_hash', 'selection'])
                || $value['source_policy_hash'] !== CanonicalJson::hash($policy)
                || $value['own_revenue_result_hash'] !== CanonicalJson::hash($own)
                || $value['own_proof_hash'] !== $line['proof_hash']
                || CanonicalJson::encode($value['document']) !== CanonicalJson::encode($upstream[0]['value']['document'])
                || ! $this->day($proof['valid_from']) || ! $this->day($proof['valid_to'])
                || $proof['valid_from'] > min($line['recognized_on'], $value['document']['iss_supply_on'])
                || $proof['valid_to'] < max($line['recognized_on'], $value['document']['iss_supply_on'])) {
                return $this->stop('FRESH_DOCUMENT_CONTEXT_AND_COVERAGE_REQUIRED');
            }
            $selection = $value['selection'];
            $export = $line['own_contribution_treatment'] === 'SERVICE_EXPORT_WITH_DIVISAS';
            $cst = $policy['cst_by_own_treatment'][$line['own_contribution_treatment']];
            if (! is_array($selection) || ! $this->keys($selection, ['pis_cst', 'cofins_cst', 'export_receipt_code', 'review_reference'])
                || $selection['pis_cst'] !== $cst || $selection['cofins_cst'] !== $cst
                || $selection['export_receipt_code'] !== ($export ? '402' : null)
                || ! $this->reference($selection['review_reference'])) {
                return $this->stop('EXPLICIT_REVIEWED_OWN_CST_SELECTION_REQUIRED');
            }
            $fields = ['CST' => $cst];
            if (! $export) {
                $fields += ['vBCPisCofins' => $line['own_contribution_base_brl'], 'pAliqPis' => $line['pis_rate_percent'], 'pAliqCofins' => $line['cofins_rate_percent']];
            }
            $fields += ['vPis' => $line['own_incident_amounts_brl']['PIS'], 'vCofins' => $line['own_incident_amounts_brl']['COFINS']];
            $documents[$role] = ['role' => $role, 'document_reference' => $line['document_reference'],
                'piscofins' => $fields, 'declaration_hash' => $proof['source_hash'], 'own_proof_hash' => $line['proof_hash']];
        }
        ksort($documents);

        return ['status' => 'STAGED_OWN_CONTRIBUTION_FIELDS_QUALIFIED', 'documents' => array_values($documents),
            'source_policy_hash' => CanonicalJson::hash($policy), 'own_result_hash' => CanonicalJson::hash($own),
            'binding_hash' => CanonicalJson::hash(['documents' => $documents, 'policy' => $policy, 'own' => $own['binding_hash']]),
            'eligible_for_integration' => false,
            'withheld_amount_used_as_own_debit' => false, 'monthly_liability_calculated' => false,
            'judicial_temporal_effects_approved' => false, 'document_obligation_complete' => false, 'production_ready' => false];
    }

    /** @param array<string, mixed> $p */
    private function captured(array $p, DateTimeImmutable $known): bool
    {
        if (! $this->keys($p, ['contract', 'evidence_id', 'observed_at', 'valid_from', 'valid_to', 'revoked_at', 'superseded_at', 'value', 'origin', 'source_hash'])
            || $p['contract'] !== self::CONTRACT || ! $this->reference($p['evidence_id']) || ! is_array($p['value'])) {
            return false;
        }
        $observed = $this->instant($p['observed_at']);
        if ($observed === null || $observed > $known || $observed < new DateTimeImmutable(self::AVAILABLE_AT)) {
            return false;
        }
        foreach (['revoked_at', 'superseded_at'] as $field) {
            if ($p[$field] !== null && ($this->instant($p[$field]) === null || $this->instant($p[$field]) <= $known)) {
                return false;
            }
        }
        $origin = $p['origin'];
        $body = array_diff_key($p, ['origin' => true, 'source_hash' => true]);

        return is_array($origin) && $this->keys($origin, ['reference', 'media_type', 'content', 'content_sha256'])
            && $this->reference($origin['reference']) && $origin['media_type'] === 'application/json'
            && $origin['content'] === CanonicalJson::encode($body)
            && $origin['content_sha256'] === hash('sha256', $origin['content'])
            && $p['source_hash'] === CanonicalJson::hash(array_diff_key($p, ['source_hash' => true]));
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

    private function day(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function reference(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    /** @param array<array-key, mixed> $value
     * @param  list<string>  $keys
     */
    private function keys(array $value, array $keys): bool
    {
        return count($value) === count($keys) && array_diff(array_keys($value), $keys) === [];
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'OWN137_'.$reason, 'document_obligation_complete' => false, 'production_ready' => false];
    }
}
