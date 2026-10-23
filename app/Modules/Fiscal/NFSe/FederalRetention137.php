<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\NFSe;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use DateTimeImmutable;

/** Arithmetic/format candidate only:integration requires fresh per-document replay and source qualification. */
final readonly class FederalRetention137
{
    public const AVAILABLE_AT = '2026-10-23T18:02:44+00:00';

    /**
     * @return array<string, mixed>
     */
    public static function policy(): array
    {
        return ['contract' => 'LOCAL-FEDERAL-RETENTION-137-V3', 'available_at' => self::AVAILABLE_AT,
            'layout_sha256' => '103a150dd6f56ec8edcaa3a453b59a1b0e096d7cd5025d7bd06a387f141ddf39',
            'layout_locator' => 'LEIAUTE DPS_NFS-e - RT!H359:I362;RN DPS_NFS-e!D677:I678',
            'nt007' => ['url' => 'https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/rtc/nt-007-se-cgnfse-v1-0.pdf',
                'page' => 6, 'primary_reader_semantics_verified' => true, 'raw_original_bytes_captured' => true, 'source_file' => 'retention137-nt007-v1-0.pdf', 'sha256' => '24e46b39fcf73d40b8241147b2c0607159893fd5934f16f63f3103343972cf05'],
            'codes_by_mask' => [0 => '0', 1 => '5', 2 => '6', 3 => '4', 4 => '8', 5 => '9', 6 => '7', 7 => '3'],
            'vRetCSLL_components' => ['PIS', 'COFINS', 'CSLL'], 'own_debits_from_withholding' => false,
            'activation' => 'REQUIRES_FRESH_BOUND_DOCUMENT_PAYMENT_COORDINATOR'];
    }

    /**
     * @param  array<array-key, mixed>  $federal
     * @param  array<array-key, mixed>  $policy
     * @return array<string, mixed>
     */
    public function project(array $federal, array $policy, DateTimeImmutable $known, string $fiscalRoot): array
    {
        if (CanonicalJson::encode($policy) !== CanonicalJson::encode(self::policy())) {
            return $this->stop('EXACT_POLICY_REQUIRED');
        }
        if ($known < new DateTimeImmutable(self::AVAILABLE_AT)) {
            return $this->stop('POLICY_NOT_KNOWN');
        }
        $source = $fiscalRoot.'/sources/official/export135-anexovi10401.xlsx';
        if (! is_file($source) || hash_file('sha256', $source) !== $policy['layout_sha256']) {
            return $this->stop('CURRENT_LAYOUT_BYTES_REQUIRED');
        }
        $nt = $fiscalRoot.'/sources/official/'.$policy['nt007']['source_file'];
        if (! is_file($nt) || hash_file('sha256', $nt) !== $policy['nt007']['sha256']) {
            return $this->stop('ORIGINAL_NT007_BYTES_REQUIRED');
        }
        if (($federal['status'] ?? null) !== 'RESOLVED' || ($federal['missing_facts'] ?? null) !== []
            || ! is_array($federal['irrf'] ?? null) || ! is_array($federal['inss'] ?? null)
            || ! in_array($federal['pcc']['status'] ?? null, ['WITHHOLD', 'PARTIAL', 'NOT_WITHHELD', 'RESOLVED', 'NOT_APPLICABLE', 'EXEMPT', 'IMMUNE', 'ZERO_RATE', 'SUSPENDED', 'DISPENSED_THRESHOLD'], true)
            || ($federal['pcc']['missing_facts'] ?? []) !== []
            || ! is_array($federal['pcc']['decisions'] ?? null) || ! array_is_list($federal['pcc']['decisions'])
            || count($federal['pcc']['decisions']) !== 3 || ! is_array($federal['additional_decisions'] ?? [])
            || ! array_is_list($federal['additional_decisions'] ?? [])) {
            return $this->stop('COMPLETE_RESOLVED_FEDERAL_ASSESSMENT_REQUIRED');
        }
        $pcc = [];
        foreach ($federal['pcc']['decisions'] as $decision) {
            if (! is_array($decision) || ! in_array($decision['tax'] ?? null, ['PIS', 'COFINS', 'CSLL'], true) || isset($pcc[$decision['tax']])) {
                return $this->stop('THREE_DISTINCT_PCC_DECISIONS_REQUIRED');
            }
            $pcc[$decision['tax']] = $decision;
        }
        if (($federal['irrf']['tax'] ?? null) !== 'IRRF' || ($federal['inss']['tax'] ?? null) !== 'INSS') {
            return $this->stop('EXPLICIT_IRRF_INSS_TAX_IDENTITIES_REQUIRED');
        }
        $zero = DecimalValue::fromString('0.00');
        $totals = array_fill_keys(['PIS', 'COFINS', 'CSLL', 'IRRF', 'INSS'], $zero);
        $pccTotal = $zero;
        $mask = 0;
        $all = [...array_values($pcc), $federal['irrf'], $federal['inss'], ...($federal['additional_decisions'] ?? [])];
        foreach ($all as $index => $decision) {
            if (! is_array($decision) || ! array_key_exists($decision['tax'] ?? '', $totals)
                || ! in_array($decision['status'] ?? null, ['WITHHOLD', 'NOT_APPLICABLE', 'EXEMPT', 'IMMUNE', 'ZERO_RATE', 'SUSPENDED', 'DISPENSED_THRESHOLD'], true)
                || ($decision['missing_facts'] ?? null) !== []) {
                return $this->stop('FINAL_EXPLICIT_COMPONENTS_REQUIRED');
            }
            $amount = $decision['amount'] ?? null;
            if ($decision['status'] === 'WITHHOLD') {
                if (! $this->money($amount) || DecimalValue::fromString($amount)->compareTo($zero) <= 0) {
                    return $this->stop('EXACT_WITHHELD_AMOUNT_REQUIRED');
                }
                $value = DecimalValue::fromString($amount);
                $totals[$decision['tax']] = $totals[$decision['tax']]->plus($value);
                if ($index < 3) {
                    $pccTotal = $pccTotal->plus($value);
                }
                $mask |= match ($decision['tax']) {
                    'PIS' => 1,'COFINS' => 2,'CSLL' => 4,default => 0
                };
            } elseif ($amount !== null && $amount !== '0.00') {
                return $this->stop('NONWITHHELD_COMPONENT_CANNOT_DROP_POSITIVE_AMOUNT');
            }
        }
        $declaredPcc = $federal['pcc']['total_withholding'] ?? null;
        if (($declaredPcc === null && $pccTotal->compareTo($zero) !== 0)
            || ($declaredPcc !== null && (! $this->money($declaredPcc) || $pccTotal->compareTo(DecimalValue::fromString($declaredPcc)) !== 0))) {
            return $this->stop('PRIMARY_PCC_TOTAL_CONFLICT');
        }
        $total = $zero;
        foreach ($totals as $amount) {
            $total = $total->plus($amount);
        }
        if (! $this->money($federal['total_withholding'] ?? null) || $total->compareTo(DecimalValue::fromString($federal['total_withholding'])) !== 0) {
            return $this->stop('ALL_FEDERAL_COMPONENTS_TOTAL_CONFLICT');
        }
        if (! $this->money($federal['gross_payment'] ?? null) || ! $this->money($federal['net_payment'] ?? null)
            || $total->compareTo(DecimalValue::fromString($federal['gross_payment'])) > 0
            || DecimalValue::fromString($federal['gross_payment'])->minus($total)->compareTo(DecimalValue::fromString($federal['net_payment'])) !== 0) {
            return $this->stop('PAYMENT_NET_AND_GROSS_CONFLICT');
        }
        $fields = ['piscofins.tpRetPisCofins' => $policy['codes_by_mask'][$mask]];
        $social = $totals['PIS']->plus($totals['COFINS'])->plus($totals['CSLL']);
        // E0724 requires this field for every nonzero supported retained-component mask.
        if ($mask !== 0) {
            $fields['vRetCSLL'] = $social->rounded(2)->value();
        }
        foreach (['INSS' => 'vRetCP', 'IRRF' => 'vRetIRRF'] as $tax => $name) {
            if ($totals[$tax]->compareTo($zero) > 0) {
                $fields[$name] = $totals[$tax]->rounded(2)->value();
            }
        }

        return ['status' => 'STAGED_FEDERAL_RETENTION_FIELDS_COMPUTED', 'fields' => $fields,
            'withheld_components_brl' => array_map(static fn (DecimalValue $a): string => $a->rounded(2)->value(), $totals),
            'retained_social_contributions_brl' => $social->rounded(2)->value(), 'total_withholding_brl' => $total->rounded(2)->value(),
            'source_policy_hash' => CanonicalJson::hash($policy), 'federal_evaluation_hash' => CanonicalJson::hash($federal),
            'own_pis_cofins_fields_emitted' => false, 'withholding_used_as_own_incidence' => false,
            'per_document_replay_binding_qualified' => false, 'nt007_original_bytes_captured' => true,
            'eligible_for_integration' => false, 'document_obligation_complete' => false, 'production_ready' => false];
    }

    private function money(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A(?:0|[1-9][0-9]{0,14})\.[0-9]{2}\z/', $value) === 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RETENTION137_'.$reason, 'eligible_for_integration' => false, 'production_ready' => false];
    }
}
