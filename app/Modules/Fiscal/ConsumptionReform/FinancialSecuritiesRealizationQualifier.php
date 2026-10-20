<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;
use InvalidArgumentException;

/** Derives current taxable/deductible components from a qualified realization and its prior recognition. */
final readonly class FinancialSecuritiesRealizationQualifier
{
    public const CONTRACT = 'LC214-TVM-REALIZATION-QUALIFIED-2026.10.06-1';

    /** @param array<string,mixed> $entry
     * @param  array<string,mixed>  $ledger
     * @param  array<string,mixed>  $rule
     * @return array<string,mixed>
     */
    public function resolve(array $entry, array $ledger, DateTimeImmutable $knownAt, array $rule): array
    {
        try {
            if (($rule['contract'] ?? null) !== self::CONTRACT || ($rule['status'] ?? null) !== 'ACTIVE'
                || ! is_string($rule['published_at'] ?? null) || new DateTimeImmutable($rule['published_at']) > $knownAt
                || ! $this->hash($rule['source_hash'] ?? null) || ! $this->hash($rule['document_source_hash'] ?? null)
                || ! is_array($rule['analysis'] ?? null) || ($rule['analysis']['historical_snapshot_required'] ?? null) !== true) {
                return $this->stop('RULE_UNAVAILABLE');
            }
            $detail = $entry['securities_realization'] ?? null;
            if (($entry['kind'] ?? null) !== 'QUALIFIED_SECURITIES_REALIZATION' || ! is_array($detail)
                || ! is_array($detail['analysis_input'] ?? null) || ($ledger['family'] ?? null) !== 'CREDIT_FX_SECURITIES_ART192'
                || ($ledger['cooperative_art271_option'] ?? null) !== false
                || ! is_array($ledger['entries'] ?? null) || ! array_is_list($ledger['entries'])) {
                return $this->stop('QUALIFIED_ENTRY_SCOPE_UNPROVEN');
            }
            $input = $detail['analysis_input'];
            $analysis = (new FinancialSecuritiesRealizationAnalyzer)->analyze($input, $rule['analysis'], $knownAt);
            if (($analysis['status'] ?? null) !== 'RECONCILED') {
                return $analysis;
            }
            if ($analysis['supplier_id'] !== ($entry['supplier_id'] ?? null) || $analysis['supplier_id'] !== ($ledger['supplier_id'] ?? null)
                || $analysis['period'].'-01' !== ($ledger['period_start'] ?? null)
                || (new DateTimeImmutable($analysis['period'].'-01'))->format('Y-m-t') !== ($ledger['period_end'] ?? null)) {
                return $this->stop('CURRENT_PERIOD_OR_SUPPLIER_CONFLICT');
            }
            $publicYield = null;
            $publicYieldId = $detail['public_yield_entry_id'] ?? null;
            if ($analysis['public_offer_art195']) {
                if (($rule['public_curve_origin_policy'] ?? null) !== 'PUBLIC_REALIZATION_WITH_SEPARATELY_QUALIFIED_D2101_CURVE'
                    || ! is_string($publicYieldId) || ! is_array($rule['public_offer_debt'] ?? null)) {
                    return $this->stop('PUBLIC_D2101_REALIZATION_ACCOUNTING_BRIDGE_PENDING');
                }
                $matches = array_values(array_filter($ledger['entries'], static fn (mixed $other): bool => is_array($other) && ($other['id'] ?? null) === $publicYieldId));
                if (count($matches) !== 1 || ($matches[0]['public_offer_debt']['sale_recognition']['companion_parent_id'] ?? null) !== $entry['id']) {
                    return $this->stop('PUBLIC_CURVE_COMPANION_SCOPE_UNPROVEN');
                }
                $publicYield = (new FinancialPublicOfferDebtQualifier)->resolve($matches[0], $ledger, $knownAt, $rule['public_offer_debt']);
                if (($publicYield['status'] ?? null) !== 'RESOLVED') {
                    return $publicYield;
                }
                if (($publicYield['sale_recognition_binding']['analysis_input_hash'] ?? null) !== CanonicalJson::hash($input)
                    || ($publicYield['sale_recognition_binding']['fair_value_tax_origin'] ?? null) !== 'D1101') {
                    return $this->stop('PUBLIC_CURVE_ORIGIN_CONFLICT');
                }
            } elseif ($publicYieldId !== null) {
                return $this->stop('UNEXPECTED_PUBLIC_CURVE_COMPANION');
            }
            if (($input['position']['first_acquisition'] ?? null) !== true && ! $analysis['historical_fiscal_snapshot_replay_performed']) {
                return $this->stop('ACTUAL_PRIOR_FISCAL_REPLAY_REQUIRED');
            }
            $policy = $rule['analysis']['daily_memory_calculation'];
            $memory = (new FinancialDebtDailyMemoryCalculator)->calculate($input['position'], $policy['series'], $knownAt, $policy);
            if (($memory['status'] ?? null) !== 'RESOLVED' || CanonicalJson::hash($memory) !== $analysis['daily_memory_hash']) {
                return $this->stop('CURRENT_DAILY_MEMORY_CONFLICT');
            }
            $identity = new FinancialPublicOfferDebtQualifier;
            $curveRows = array_map(static fn (array $day): array => array_intersect_key($day, array_flip(['date', 'contract_curve_id', 'appropriation'])), $memory['daily_memory']);
            $curveValue = ['supplier_id' => $ledger['supplier_id'], 'period' => $analysis['period'], 'title' => $analysis['title'],
                'daily_memory_hash' => $analysis['daily_memory_hash'], 'appropriation_excludes_fair_value' => true, 'curve_rows' => $curveRows];
            if (! $identity->proof($detail['produced_curve_evidence'] ?? null, $curveValue, $ledger['supplier_id'], $ledger['period_start'], $ledger['period_end'], $knownAt)) {
                return $this->stop('PRODUCED_CURVE_CLASSIFICATION_UNPROVEN');
            }
            $value = ['supplier_id' => $ledger['supplier_id'], 'period_start' => $ledger['period_start'], 'period_end' => $ledger['period_end'],
                'financial_entry_id' => $entry['id'], 'document_id' => $entry['document_id'], 'amount' => $entry['amount'],
                'analysis_input_hash' => CanonicalJson::hash($input)];
            // The ledger hash omits this envelope to avoid a self-referential proof.
            $value['ledger_input_hash'] = CanonicalJson::hash($this->unsealedLedger($ledger));
            if (! $identity->proof($detail['qualification_evidence'] ?? null, $value, $ledger['supplier_id'], $ledger['period_start'], $ledger['period_end'], $knownAt)) {
                return $this->stop('INDEPENDENT_CURRENT_QUALIFICATION_UNPROVEN');
            }
            $zero = BigDecimal::of('0.00000000');
            $curveCredit = $curveDebit = $zero;
            foreach ($memory['daily_memory'] as $day) {
                $amount = $this->decimal($day['appropriation']);
                if ($amount->isNegative()) {
                    $curveDebit = $curveDebit->plus($amount->abs());
                } else {
                    $curveCredit = $curveCredit->plus($amount);
                }
            }
            if (($analysis['title']['instrument_type'] ?? null) === 'EQUITY' && (! $curveCredit->isZero() || ! $curveDebit->isZero())) {
                return $this->stop('EQUITY_PRODUCED_RETURN_CLASSIFICATION_PENDING');
            }
            $components = [];
            if ($publicYield === null && ! $curveCredit->isZero()) {
                $components[] = $this->component('CURVE_CREDIT', 'CURRENT_PRODUCED_RETURN', $curveCredit, '311011104', 'LC214 art185; art192 caput; art195 caput');
            }
            if ($publicYield === null && ! $curveDebit->isZero()) {
                $components[] = $this->component('CURVE_DEBIT', 'CURRENT_PRODUCED_RETURN', $curveDebit->negated(), '411011102', 'LC214 art187; art192 caput III');
            }
            if (! $curveCredit->toScale(2, RoundingMode::HalfEven)->minus($curveDebit->toScale(2, RoundingMode::HalfEven))->isEqualTo($memory['monthly']['vVarMensal'])) {
                if ($publicYield === null || ($publicYield['gross_rounding_reconciliation']['status'] ?? null) !== 'RESOLVED'
                    || $publicYield['gross_rounding_reconciliation']['daily_memory_hash'] !== CanonicalJson::hash($memory)
                    || ! $curveCredit->isEqualTo($publicYield['gross_rounding_reconciliation']['raw_gross_credit'])
                    || ! $curveDebit->isEqualTo($publicYield['gross_rounding_reconciliation']['raw_gross_debit'])) {
                    return $this->stop('GROSS_ROUNDING_RECONCILIATION_PENDING');
                }
            }
            foreach ($analysis['realizations'] as $sale) {
                foreach (['new_produced_return_at_sale' => 'DISPOSAL_NEW_PRODUCED_RETURN',
                    'deferred_fair_value_realized' => 'DEFERRED_FAIR_VALUE_REALIZED', 'new_fair_value_change_at_sale' => 'NEW_FAIR_VALUE_REALIZED'] as $key => $kind) {
                    $amount = $this->decimal($sale['components'][$key]);
                    if ($amount->isZero()) {
                        continue;
                    }
                    $codTrib = $amount->isNegative() ? '411011102' : ($key === 'new_produced_return_at_sale' ? '311011103' : '311011107');
                    if ($key === 'deferred_fair_value_realized' && ($rule['deferred_fair_value_code_policy'] ?? null) === 'DERE_RECOGNIZED_BY_COMPETENCE_REALIZED_311011702_411011701') {
                        $codTrib = $amount->isNegative() ? '411011701' : '311011702';
                    }
                    $components[] = $this->component('SALE:'.$sale['operation_id'].':'.$key, $kind, $amount, $codTrib,
                        $key === 'new_produced_return_at_sale' ? 'LC214 art192 paragraph1 II; caput III; art187' : 'LC214 art192 paragraph6; art187');
                }
            }
            $otherRealizations = [];
            $operations = [];
            foreach ($memory['daily_memory'] as $day) {
                foreach ($day['operations'] as $operation) {
                    $operations[$operation['id']] = ['date' => $day['date'], ...$operation];
                }
            }
            foreach ($input['recognition_inventory']['other_releases'] as $release) {
                $amount = $this->decimal($release['components']['deferred_fair_value_realized']);
                if ($amount->isZero()) {
                    continue;
                }
                if (($rule['other_release_realization_mode'] ?? null) !== 'PROVEN_AMORTIZATION_WITH_PRIOR_HISTORY_AND_SUBACCOUNT') {
                    return $this->stop('OTHER_FAIR_VALUE_REALIZATION_PENDING');
                }
                $event = $operations[$release['operation_id']];
                $label = $release['fair_value_subaccount'] ?? null;
                if ($event['kind'] !== 'AMORTIZATION' || ! is_string($label) || preg_match('/^[A-Za-z0-9]{1,53}$/', $label) !== 1
                    || $label === $analysis['title']['cCta'] || ! $analysis['historical_fiscal_snapshot_replay_performed']) {
                    return $this->stop('OTHER_FAIR_VALUE_REALIZATION_SCOPE_UNPROVEN');
                }
                $releaseValue = ['supplier_id' => $ledger['supplier_id'], 'period' => $analysis['period'], 'title' => $analysis['title'],
                    'operation' => $event, 'components' => $release['components'], 'fair_value_subaccount' => $label,
                    'fiscal_components' => $release['fiscal_components'] ?? null,
                    'prior_source_set_hash' => $analysis['replayed_fiscal_history']['source_set_hash'],
                    'realization_confirmed' => true, 'daily_memory_hash' => $analysis['daily_memory_hash']];
                if (! $identity->proof($release['realization_evidence'] ?? null, $releaseValue, $ledger['supplier_id'], $ledger['period_start'], $ledger['period_end'], $knownAt)) {
                    return $this->stop('INDEPENDENT_OTHER_FAIR_VALUE_REALIZATION_UNPROVEN');
                }
                $components[] = $this->component('RELEASE:'.$event['id'].':deferred_fair_value_realized', 'DEFERRED_FAIR_VALUE_REALIZED', $amount,
                    $amount->isNegative() ? '411011701' : '311011702', 'LC214 art192 paragraph6; art187');
                $otherRealizations[] = ['operation_id' => $event['id'], 'date' => $event['date'], 'kind' => $event['kind'],
                    'fair_value_subaccount' => $label, 'components' => ['deferred_fair_value_realized' => (string) $amount, 'new_fair_value_change_at_sale' => '0'],
                    'prior_return_not_reincluded' => $release['components']['prior_produced_return'],
                    'current_curve_return_not_reincluded' => $release['components']['current_produced_return_already_in_curve'],
                    'realization_evidence_hash' => $release['realization_evidence']['source_hash']];
            }
            usort($components, static fn (array $a, array $b): int => $a['component_id'] <=> $b['component_id']);
            $lossProofs = $detail['deduction_evidence'] ?? [];
            if (! is_array($lossProofs) || ! array_is_list($lossProofs)) {
                return $this->stop('LOSS_QUALIFICATION_LIST_INVALID');
            }
            $losses = [];
            foreach ($lossProofs as $proof) {
                if (! is_array($proof) || ! is_string($proof['component_id'] ?? null) || isset($losses[$proof['component_id']])) {
                    return $this->stop('LOSS_QUALIFICATION_UNKNOWN_OR_DUPLICATE');
                }
                $losses[$proof['component_id']] = $proof;
            }
            $revenue = $deductions = $gross = BigDecimal::of('0.00');
            $derived = [];
            foreach ($components as $index => $component) {
                $amount = BigDecimal::of($component['amount']);
                if ($component['bucket'] === 'DEDUCTION') {
                    $proof = $losses[$component['component_id']] ?? null;
                    if (! is_array($proof) || ! is_array($proof['conditions'] ?? null)
                        || ($proof['conditions']['operation_authorized'] ?? null) !== true
                        || ($proof['conditions']['within_operational_limits'] ?? null) !== true
                        || ($proof['conditions']['not_administrative_expense'] ?? null) !== true
                        || ! is_string($proof['conditions']['authorization_evidence_id'] ?? null) || trim($proof['conditions']['authorization_evidence_id']) === '') {
                        return $this->stop('LOSS_AUTHORIZATION_UNPROVEN');
                    }
                    $lossValue = ['supplier_id' => $ledger['supplier_id'], 'period' => $analysis['period'], 'title' => $analysis['title'],
                        'financial_entry_id' => $entry['id'], 'component' => $component, 'conditions' => $proof['conditions'], 'analysis_input_hash' => CanonicalJson::hash($input)];
                    if (! $identity->proof($proof['independent_evidence'] ?? null, $lossValue, $ledger['supplier_id'], $ledger['period_start'], $ledger['period_end'], $knownAt)) {
                        return $this->stop('INDEPENDENT_LOSS_QUALIFICATION_UNPROVEN');
                    }
                    unset($losses[$component['component_id']]);
                    $deductions = $deductions->plus($amount);
                } else {
                    $revenue = $revenue->plus($amount);
                }
                $gross = $gross->plus($amount);
                $derived[] = ['id' => $entry['id'].':TVM:'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'supplier_id' => $entry['supplier_id'], 'document_id' => $entry['document_id'], 'recognized_on' => $entry['recognized_on'],
                    'qualification_evidence_id' => $detail['qualification_evidence']['evidence_id'], 'kind' => 'QUALIFIED_SECURITIES_COMPONENT',
                    'computed_amount' => $component['amount'], 'bucket' => $component['bucket'], 'source_article' => $component['source_article'],
                    'realization_parent_id' => $entry['id'], 'realization_component' => $component,
                    'security_identity' => array_intersect_key($analysis['title'], array_flip(['idTitulo', 'cCta', 'CNPJDevedor', 'currency'])),
                    'realization_binding' => ['status' => 'RESOLVED', 'contract' => self::CONTRACT, 'analysis_hash' => CanonicalJson::hash($analysis),
                        'parent_input_hash' => CanonicalJson::hash($entry), 'qualification_evidence_hash' => $detail['qualification_evidence']['source_hash'],
                        'expected_codTrib' => $component['expected_codTrib'], 'tax_origin' => 'D1101', 'production_ready' => false]];
            }
            if ($losses !== []) {
                return $this->stop('LOSS_QUALIFICATION_UNKNOWN_OR_DUPLICATE');
            }
            if (! $gross->isEqualTo($this->decimal($entry['amount'] ?? null))) {
                return $this->stop('DECLARED_COMPONENT_GROSS_AMOUNT_CONFLICT');
            }

            return ['status' => 'RESOLVED', 'reason' => 'RTC_TVM_CURRENT_COMPONENTS_QUALIFIED', 'contract' => self::CONTRACT,
                'supplier_id' => $ledger['supplier_id'], 'period' => $analysis['period'], 'title' => $analysis['title'],
                'revenue' => (string) $revenue->toScale(2), 'deductions' => (string) $deductions->toScale(2),
                'component_gross' => (string) $gross->toScale(2), 'net_base' => (string) $revenue->minus($deductions)->toScale(2),
                'financial_entries' => $derived, 'analysis' => $analysis, 'components' => $components,
                ...($publicYield === null ? [] : ['public_yield_entry_id' => $publicYieldId, 'public_yield_binding' => $publicYield]),
                ...($otherRealizations === [] ? [] : ['other_release_realizations' => $otherRealizations]),
                'source_hash' => $rule['source_hash'], 'document_source_hash' => $rule['document_source_hash'],
                'historical_fiscal_snapshot_replay_performed' => $analysis['historical_fiscal_snapshot_replay_performed'],
                'scope' => 'QUALIFIED_CURRENT_TVM_COMPONENTS_NO_TAX_RATE_OR_DOCUMENT_APPROVAL', 'production_ready' => false, 'posting_performed' => false];
        } catch (InvalidArgumentException|\DateMalformedStringException) {
            return $this->stop('AMOUNT_OR_DATE_INVALID');
        }
    }

    /** @param array<string,mixed> $ledger
     * @return array<string,mixed>
     */
    private function unsealedLedger(array $ledger): array
    {
        foreach ($ledger['entries'] as &$entry) {
            if (is_array($entry['securities_realization'] ?? null)) {
                unset($entry['securities_realization']['qualification_evidence']);
            }
        }
        unset($entry);

        return $ledger;
    }

    /** @return array<string,mixed> */
    private function component(string $id, string $kind, BigDecimal $amount, string $code, string $article): array
    {
        return ['component_id' => $id, 'component_kind' => $kind, 'signed_unrounded_amount' => (string) $amount->toScale(8, RoundingMode::HalfEven),
            'amount' => (string) $amount->abs()->toScale(2, RoundingMode::HalfEven), 'bucket' => $amount->isNegative() ? 'DEDUCTION' : 'REVENUE',
            'expected_codTrib' => $code, 'source_article' => $article];
    }

    private function decimal(mixed $value): BigDecimal
    {
        if (! is_string($value) || preg_match('/^-?(0|[1-9][0-9]{0,14})(\.[0-9]{1,8})?$/', $value) !== 1) {
            throw new InvalidArgumentException('Canonical amount required.');
        }

        return BigDecimal::of($value);
    }

    private function hash(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1;
    }

    /** @return array<string,mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_TVM_CURRENT_'.$reason, 'production_ready' => false];
    }
}
