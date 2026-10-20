<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use InvalidArgumentException;

/** Calculates borrower credits from qualified paid operations and supplier reports, arts190/194-199. */
final readonly class FinancialCreditEngine
{
    /** @param array<string, mixed> $proof
     * @param  array<string, mixed>  $tax
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    public function resolve(array $proof, array $tax, string $supplierId, string $acquirerId, string $documentId, LocalDate $on, DateTimeImmutable $knownAt, array $rule): array
    {
        if (($rule['strategy'] ?? null) !== 'LC214_FINANCIAL_BORROWER_CREDIT_ART190_194_199' || ($rule['status'] ?? null) !== 'ACTIVE'
            || ! isset($rule['published_at'], $rule['valid_from']) || $knownAt < new DateTimeImmutable($rule['published_at'])
            || $on->value() < $rule['valid_from'] || (isset($rule['valid_to']) && $on->value() > $rule['valid_to'])
            || (isset($rule['revoked_at']) && $knownAt >= new DateTimeImmutable($rule['revoked_at']))
            || (isset($rule['superseded_at']) && $knownAt >= new DateTimeImmutable($rule['superseded_at']))
            || ! is_string($rule['source_hash'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $rule['source_hash'])) {
            return $this->stop('ACTION_REQUIRED', 'RULE_NOT_AVAILABLE');
        }
        if (($proof['supplier_id'] ?? null) !== $supplierId || ($proof['acquirer_id'] ?? null) !== $acquirerId
            || ($proof['document_id'] ?? null) !== $documentId || $supplierId === '' || $acquirerId === '' || $documentId === '') {
            return $this->stop('INVALID', 'PARTIES_OR_DOCUMENT_CONFLICT');
        }
        $route = $proof['route'] ?? null;
        if (! in_array($route, ['LOAN_INSTALMENT_ART194', 'DEBT_ISSUE_ART195', 'RECEIVABLE_DISCOUNT_ART196', 'FEES_ART198', 'OTHER_ART199'], true)) {
            return $this->stop('ACTION_REQUIRED', 'ACQUISITION_ROUTE_UNPROVEN');
        }
        if (! in_array($proof['acquirer_regime'] ?? null, ['REGULAR', 'SIMPLES_NACIONAL', 'MEI', 'NON_TAXPAYER'], true)) {
            return $this->stop('ACTION_REQUIRED', 'ACQUIRER_REGIME_UNPROVEN');
        }
        if ($proof['acquirer_regime'] !== 'REGULAR') {
            return $this->denied('ACQUIRER_NOT_REGULAR');
        }
        if (! is_bool($proof['not_personal_use'] ?? null)) {
            return $this->stop('ACTION_REQUIRED', 'PERSONAL_USE_STATUS_UNPROVEN');
        }
        if (! $proof['not_personal_use']) {
            return $this->denied('PERSONAL_USE_ART57');
        }
        if ($route === 'OTHER_ART199') {
            return $this->denied('NO_EXPRESS_PERMISSION_ART199');
        }
        if (! is_bool($proof['acquirer_in_section_specific_regime'] ?? null)) {
            return $this->stop('ACTION_REQUIRED', 'ACQUIRER_SPECIFIC_REGIME_UNPROVEN');
        }
        if ($route !== 'FEES_ART198' && $proof['acquirer_in_section_specific_regime']) {
            return $this->denied('ACQUIRER_IN_SECTION_SPECIFIC_REGIME');
        }
        $category = $proof['operation_category'] ?? null;
        if (($route === 'LOAN_INSTALMENT_ART194' && $category !== 'CREDIT')
            || ($route === 'DEBT_ISSUE_ART195' && $category !== 'SECURITIES')
            || ($route === 'RECEIVABLE_DISCOUNT_ART196' && ! in_array($category, ['CREDIT', 'SECURITIZATION', 'FACTORING'], true))
            || ($route === 'FEES_ART198' && ! in_array($category, ['CREDIT', 'FX', 'SECURITIES', 'SECURITIZATION', 'FACTORING'], true))) {
            return $this->stop('INVALID', 'ROUTE_OPERATION_CONFLICT');
        }
        if ($route !== 'FEES_ART198') {
            if (! is_bool($proof['cooperative_option271_member_borrower'] ?? null)) {
                return $this->stop('ACTION_REQUIRED', 'COOPERATIVE_BORROWER_STATUS_UNPROVEN');
            }
            if ($proof['cooperative_option271_member_borrower'] && $category === 'CREDIT') {
                return $this->denied('COOPERATIVE_MEMBER_ART197_I');
            }
            if ($category === 'CREDIT' || $route === 'DEBT_ISSUE_ART195') {
                if (! is_bool($proof['foreign_currency_referenced'] ?? null)) {
                    return $this->stop('ACTION_REQUIRED', 'CURRENCY_REFERENCE_UNPROVEN');
                }
                if ($proof['foreign_currency_referenced']) {
                    return $this->denied('FOREIGN_CURRENCY_ART197_II');
                }
            }
        }
        if ($route === 'DEBT_ISSUE_ART195') {
            if (($proof['held_by_specific_regime_creditor_during_payment_period'] ?? null) !== true
                || ! $this->evidence($proof['creditor_holding_period_evidence_id'] ?? null)) {
                return $this->stop('ACTION_REQUIRED', 'CREDITOR_HOLDING_PERIOD_UNPROVEN');
            }
            if (! is_bool($proof['public_debt_exception_qualified'] ?? null)
                || ! $this->evidence($proof['public_debt_exception_evidence_id'] ?? null)) {
                return $this->stop('ACTION_REQUIRED', 'PUBLIC_DEBT_EXCEPTION_UNPROVEN');
            }
            if ($proof['public_debt_exception_qualified']) {
                return $this->denied('PUBLIC_DEBT_ART195_PARAGRAPHS1_2');
            }
        }
        if ($route === 'FEES_ART198' && $proof['acquirer_in_section_specific_regime']) {
            if (! is_bool($proof['expense_deducted_from_specific_base'] ?? null)) {
                return $this->stop('ACTION_REQUIRED', 'EXPENSE_DEDUCTION_STATUS_UNPROVEN');
            }
            if ($proof['expense_deducted_from_specific_base']) {
                return $this->denied('EXPENSE_ALREADY_DEDUCTED_ART198');
            }
        }
        foreach (['operation_qualification_evidence_id', 'supplier_report_evidence_id', 'fiscal_document_evidence_id', 'credit_request_ledger_evidence_id', 'transaction_id'] as $key) {
            if (! $this->evidence($proof[$key] ?? null)) {
                return $this->stop('ACTION_REQUIRED', 'EVIDENCE_MISSING:'.$key);
            }
        }
        if (($proof['fiscal_document_valid'] ?? null) !== true || ($proof['supplier_report_bound_to_operation'] ?? null) !== true
            || ! is_string($proof['supplier_report_hash'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $proof['supplier_report_hash'])) {
            return $this->stop('ACTION_REQUIRED', 'DOCUMENT_OR_SUPPLIER_REPORT_UNPROVEN');
        }
        if (($proof['request_not_already_appropriated'] ?? null) !== true) {
            return $this->stop(($proof['request_not_already_appropriated'] ?? null) === false ? 'INVALID' : 'ACTION_REQUIRED', 'PRIOR_APPROPRIATION_CONFLICT_OR_UNPROVEN');
        }
        $base = DecimalValue::fromString('0.00');
        try {
            $observed = LocalDate::fromString((string) ($proof['observed_on'] ?? ''))->value();
            if ($observed > $knownAt->format('Y-m-d')) {
                return $this->stop('INVALID', 'OBSERVATION_CONFLICT');
            }
            if ($route !== 'FEES_ART198') {
                if (! is_bool($proof['payment_occurred'] ?? null)) {
                    return $this->stop('ACTION_REQUIRED', 'PAYMENT_STATUS_UNPROVEN');
                }
                if (! $proof['payment_occurred']) {
                    return $this->conditional('NOT_PAID_CASH_BASIS');
                }
                $paid = LocalDate::fromString((string) ($proof['paid_on'] ?? ''))->value();
                if ($paid < '2026-01-01' || $paid > $on->value() || $paid > $observed || ! $this->evidence($proof['payment_evidence_id'] ?? null)) {
                    return $this->stop('INVALID', 'PAYMENT_DATE_OR_PROOF_CONFLICT');
                }
                if ($route === 'RECEIVABLE_DISCOUNT_ART196') {
                    $gross = $this->money($proof['discount_applied'] ?? null);
                    $benchmark = $this->money($proof['qualified_di_curve_amount'] ?? null);
                    $benchmarkKind = 'DI_CURVE_FOR_ADVANCE_TERM';
                } else {
                    $gross = $this->money($proof['instalment_paid'] ?? null)->minus($this->money($proof['contractual_principal_part'] ?? null));
                    $benchmark = $this->money($proof['qualified_selic_amount'] ?? null);
                    $benchmarkKind = 'SELIC_OVERNIGHT_ON_PRINCIPAL';
                    if (! $this->evidence($proof['contractual_amortization_evidence_id'] ?? null)) {
                        return $this->stop('ACTION_REQUIRED', 'PRINCIPAL_AMORTIZATION_UNPROVEN');
                    }
                }
                if ($gross->isNegative()) {
                    return $this->stop('INVALID', 'PRINCIPAL_EXCEEDS_INSTALMENT');
                }
                if (($proof['benchmark_kind'] ?? null) !== $benchmarkKind || ($proof['benchmark_calculation_qualified'] ?? null) !== true
                    || ! $this->evidence($proof['benchmark_period_evidence_id'] ?? null)
                    || ! is_string($proof['benchmark_source_hash'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $proof['benchmark_source_hash'])) {
                    return $this->stop('ACTION_REQUIRED', 'BENCHMARK_CALCULATION_UNPROVEN');
                }
                $base = $gross->minus($benchmark);
                if ($base->isNegative()) {
                    $base = DecimalValue::fromString('0.00');
                }
            }
            $amounts = [];
            foreach (['IBS', 'CBS'] as $component) {
                $row = $proof['components'][$component] ?? null;
                if (! is_array($row) || ($row['tax'] ?? null) !== $component
                    || ! $this->evidence($row['supplier_report_component_id'] ?? null)) {
                    return $this->stop('ACTION_REQUIRED', 'COMPONENT_REPORT_UNPROVEN:'.$component);
                }
                $limit = $this->money($row['reported_credit_limit'] ?? null);
                if ($route === 'FEES_ART198') {
                    $amount = $this->money($row['supplier_tax_actually_paid_for_fee'] ?? null);
                    $paidDate = LocalDate::fromString((string) ($row['supplier_tax_paid_on'] ?? ''))->value();
                    if ($paidDate < '2026-01-01' || $paidDate > $on->value() || $paidDate > $observed) {
                        return $this->stop('INVALID', 'SUPPLIER_FEE_PAYMENT_DATE_CONFLICT:'.$component);
                    }
                    if (! $this->evidence($row['supplier_payment_evidence_id'] ?? null)) {
                        return $this->stop('ACTION_REQUIRED', 'SUPPLIER_FEE_PAYMENT_UNPROVEN:'.$component);
                    }
                } else {
                    if (($tax['status'] ?? null) !== 'RESOLVED' || ($tax['supplier_id'] ?? null) !== $supplierId) {
                        return $this->stop('ACTION_REQUIRED', 'FINANCIAL_OPERATION_RATE_UNPROVEN');
                    }
                    if (! is_string($tax['period_end'] ?? null) || substr($tax['period_end'], 0, 7) !== substr($paid, 0, 7)
                        || ! isset($tax['components']['CBS']['percent'], $tax['components']['IBS_STATE']['percent'], $tax['components']['IBS_MUNICIPAL']['percent'])) {
                        return $this->stop('INVALID', 'RATE_PAYMENT_PERIOD_CONFLICT');
                    }
                    foreach (['CBS', 'IBS_STATE', 'IBS_MUNICIPAL'] as $rateComponent) {
                        $rate = $tax['components'][$rateComponent]['percent'];
                        if (! is_string($rate) || ! preg_match('/^\d+(?:\.\d+)?$/', $rate)
                            || DecimalValue::fromString($rate)->compareTo(DecimalValue::fromString('100')) > 0) {
                            return $this->stop('INVALID', 'RATE_PERCENT_INVALID:'.$rateComponent);
                        }
                    }
                    $percent = $component === 'CBS' ? DecimalValue::fromString($tax['components']['CBS']['percent'])
                        : DecimalValue::fromString($tax['components']['IBS_STATE']['percent'])->plus(DecimalValue::fromString($tax['components']['IBS_MUNICIPAL']['percent']));
                    $amount = $base->multipliedBy($percent)->dividedBy(DecimalValue::fromString('100'), 2);
                    if (($row['extinction_qualification'] ?? null) === 'ART48_DOCUMENTAL_DISPENSATION') {
                        if (($row['split_not_implemented'] ?? null) !== true || ($row['acquirer_collection_not_implemented'] ?? null) !== true
                            || ! $this->evidence($row['implementation_status_evidence_id'] ?? null)
                            || ! is_string($row['implementation_source_hash'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $row['implementation_source_hash'])
                            || ($row['correct_fiscal_debits_qualified'] ?? null) !== true) {
                            return $this->stop('ACTION_REQUIRED', 'ART48_DISPENSATION_UNPROVEN:'.$component);
                        }
                    } elseif (($row['extinction_qualification'] ?? null) !== 'PROVEN_EXTINCTION'
                        || ! $this->evidence($row['extinction_evidence_id'] ?? null)) {
                        $amounts[$component] = ['status' => 'CONDITIONAL', 'computed_limit' => '0.00', 'requested_credit' => '0.00',
                            'theoretical_limit_pending_extinction' => $amount->value(), 'reason' => 'PRIOR_TAX_EXTINCTION_UNPROVEN',
                            'supplier_report_component_id' => $row['supplier_report_component_id']];

                        continue;
                    }
                }
                if ($amount->compareTo($limit) > 0) {
                    return $this->stop('INVALID', 'COMPUTED_CREDIT_EXCEEDS_SUPPLIER_REPORT:'.$component);
                }
                $requested = $this->money($row['requested_credit'] ?? null);
                if ($requested->compareTo($amount) > 0) {
                    return $this->stop('INVALID', 'REQUEST_EXCEEDS_ALLOWED_CREDIT:'.$component);
                }
                $amounts[$component] = ['status' => 'ALLOWED', 'computed_limit' => $amount->value(), 'requested_credit' => $requested->value(),
                    'supplier_report_limit' => $limit->value(), 'supplier_report_component_id' => $row['supplier_report_component_id']];
            }
        } catch (InvalidArgumentException) {
            return $this->stop('INVALID', 'AMOUNT_OR_DATE_INVALID');
        }

        $statuses = array_unique(array_column($amounts, 'status'));

        return ['status' => 'RESOLVED', 'reason' => 'RTC_FINANCIAL_BORROWER_CREDIT_QUALIFIED',
            'eligibility' => count($statuses) > 1 ? 'MIXED' : (in_array('CONDITIONAL', $statuses, true) ? 'CONDITIONAL' : 'ALLOWED'), 'route' => $route,
            'credit_base' => $route === 'FEES_ART198' ? null : $base->value(), 'components' => $amounts,
            'source_hash' => $rule['source_hash'], 'supplier_report_hash' => $proof['supplier_report_hash'],
            'scope' => 'QUALIFIED_ELIGIBILITY_AND_REQUEST_NO_APPROPRIATION_OR_POSTING', 'appropriation_performed' => false, 'posting_performed' => false];
    }

    private function evidence(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private function money(mixed $value): DecimalValue
    {
        if (! is_string($value) || ! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException('Nonnegative BRL amount required.');
        }

        return DecimalValue::fromString($value)->rounded(2);
    }

    /** @return array<string, mixed> */
    private function denied(string $reason): array
    {
        return ['status' => 'RESOLVED', 'reason' => 'RTC_FINANCIAL_CREDIT_'.$reason, 'eligibility' => 'DENIED',
            'components' => ['IBS' => ['status' => 'DENIED', 'requested_credit' => '0.00'], 'CBS' => ['status' => 'DENIED', 'requested_credit' => '0.00']],
            'appropriation_performed' => false, 'posting_performed' => false];
    }

    /** @return array<string, mixed> */
    private function conditional(string $reason): array
    {
        return ['status' => 'RESOLVED', 'reason' => 'RTC_FINANCIAL_CREDIT_'.$reason, 'eligibility' => 'CONDITIONAL',
            'components' => ['IBS' => ['status' => 'CONDITIONAL', 'requested_credit' => '0.00'], 'CBS' => ['status' => 'CONDITIONAL', 'requested_credit' => '0.00']],
            'appropriation_performed' => false, 'posting_performed' => false];
    }

    /** @return array<string, mixed> */
    private function stop(string $status, string $reason): array
    {
        return ['status' => $status, 'reason' => 'RTC_FINANCIAL_CREDIT_'.$reason];
    }
}
