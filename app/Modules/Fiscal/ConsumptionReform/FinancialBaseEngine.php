<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;
use InvalidArgumentException;

/** Calculates a qualified financial ledger; does not classify the supplier or determine rates. */
final readonly class FinancialBaseEngine
{
    /** @param array<string, mixed> $ledger
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    public function resolve(array $ledger, LocalDate $on, DateTimeImmutable $knownAt, array $rule): array
    {
        if (($rule['strategy'] ?? null) !== 'LC214_FINANCIAL_BASE_ART192_193' || ($rule['status'] ?? null) !== 'ACTIVE'
            || ! isset($rule['published_at'], $rule['valid_from']) || $knownAt < new DateTimeImmutable($rule['published_at'])
            || $on->value() < $rule['valid_from'] || (isset($rule['valid_to']) && $on->value() > $rule['valid_to'])
            || (isset($rule['revoked_at']) && $knownAt >= new DateTimeImmutable($rule['revoked_at']))
            || (isset($rule['superseded_at']) && $knownAt >= new DateTimeImmutable($rule['superseded_at']))
            || ! is_string($rule['source_hash'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $rule['source_hash'])) {
            return $this->stop('ACTION_REQUIRED', 'RULE_NOT_AVAILABLE');
        }
        $family = $ledger['family'] ?? null;
        if (($ledger['currency'] ?? null) !== 'BRL') {
            return $this->stop('ACTION_REQUIRED', 'CURRENCY_CONVERSION_REQUIRED');
        }
        if (! in_array($family, ['CREDIT_FX_SECURITIES_ART192', 'SECURITIZATION_FACTORING_ART193'], true)) {
            return $this->stop('ACTION_REQUIRED', 'OTHER_FINANCIAL_BASE_RULE_REQUIRED');
        }
        foreach (['ledger_complete', 'financial_scope_qualified', 'recognition_dates_qualified', 'net_of_ibs_cbs_iss'] as $key) {
            if (($ledger[$key] ?? null) !== true) {
                return $this->stop('ACTION_REQUIRED', 'CONDITION_UNPROVEN:'.$key);
            }
        }
        foreach (['supplier_id', 'ledger_evidence_id', 'scope_evidence_id', 'recognition_evidence_id', 'tax_exclusion_evidence_id'] as $key) {
            if (! $this->proof($ledger[$key] ?? null)) {
                return $this->stop('ACTION_REQUIRED', 'EVIDENCE_MISSING:'.$key);
            }
        }
        if (! is_array($ledger['entries'] ?? null) || ! array_is_list($ledger['entries'])) {
            return $this->stop('INVALID', 'ENTRIES_INVALID');
        }
        $zero = DecimalValue::fromString('0.00');
        $revenue = $deduction = $excluded = $deferred = $zero;
        $trace = $seen = $seenComponents = [];
        $seenTitles = [];
        $titleIssuers = [];
        $realizationBindings = [];
        try {
            $start = LocalDate::fromString((string) ($ledger['period_start'] ?? ''))->value();
            $end = LocalDate::fromString((string) ($ledger['period_end'] ?? ''))->value();
            $observed = LocalDate::fromString((string) ($ledger['observed_on'] ?? ''))->value();
            if ($start !== substr($on->value(), 0, 7).'-01' || $end !== (new DateTimeImmutable($start))->format('Y-m-t')
                || $end > $on->value() || $end > $observed || $observed > $knownAt->format('Y-m-d')) {
                return $this->stop('INVALID', 'PERIOD_OR_OBSERVATION_CONFLICT');
            }
            foreach ($ledger['entries'] as $entry) {
                if (! is_array($entry) || ! $this->proof($entry['id'] ?? null) || isset($seen[$entry['id']])
                    || ($entry['supplier_id'] ?? null) !== $ledger['supplier_id'] || ! $this->proof($entry['document_id'] ?? null)
                    || ! $this->proof($entry['qualification_evidence_id'] ?? null)) {
                    return $this->stop('INVALID', 'ENTRY_IDENTITY_CONFLICT');
                }
                $seen[$entry['id']] = true;
                foreach (['fiscal_origin', 'book_apur_amount', 'd2101_binding', 'realization_binding', 'realization_parent_id', 'realization_component', 'deferred_signed_amount'] as $reserved) {
                    if (array_key_exists($reserved, $entry)) {
                        return $this->stop('INVALID', 'RESERVED_DERIVED_ENTRY_METADATA');
                    }
                }
                if (isset($entry['public_offer_debt']) && ($entry['kind'] ?? null) !== 'PUBLIC_OFFER_DEBT_YIELD') {
                    return $this->stop('INVALID', 'PUBLIC_OFFER_ENTRY_KIND_CONFLICT');
                }
                if (isset($entry['securities_realization']) && ($entry['kind'] ?? null) !== 'QUALIFIED_SECURITIES_REALIZATION') {
                    return $this->stop('INVALID', 'SECURITIES_REALIZATION_ENTRY_KIND_CONFLICT');
                }
                $component = $entry['document_id'].':'.(string) ($entry['kind'] ?? '');
                if (isset($seenComponents[$component])) {
                    return $this->stop('INVALID', 'DUPLICATE_DOCUMENT_COMPONENT');
                }
                $seenComponents[$component] = true;
                $date = LocalDate::fromString((string) ($entry['recognized_on'] ?? ''))->value();
                if ($date < $start || $date > $end) {
                    return $this->stop('INVALID', 'ENTRY_OUTSIDE_PERIOD');
                }
                $amount = $this->money($entry['amount'] ?? null);
                $kind = $entry['kind'] ?? null;
                $bucket = 'REVENUE';
                $article = 'LC214 art185';
                $publicDebt = null;
                $deferredSignedAmount = null;
                if ($kind === 'QUALIFIED_SECURITIES_REALIZATION') {
                    $sameDocument = array_filter($ledger['entries'], static fn (mixed $other): bool => is_array($other)
                        && ($other['document_id'] ?? null) === $entry['document_id']);
                    if (count($sameDocument) !== 1) {
                        return $this->stop('INVALID', 'SECURITIES_REALIZATION_DUPLICATE_DOCUMENT');
                    }
                    $qualified = (new FinancialSecuritiesRealizationQualifier)->resolve($entry, $ledger, $knownAt, $rule['securities_realization'] ?? []);
                    if (($qualified['status'] ?? null) !== 'RESOLVED') {
                        return $qualified;
                    }
                    $title = $qualified['title'];
                    $key = $title['cCta'].'|'.$title['idTitulo'];
                    if (isset($seenTitles[$key]) && ($qualified['public_yield_entry_id'] ?? null) !== $seenTitles[$key]) {
                        return $this->stop('INVALID', 'SECURITIES_REALIZATION_DUPLICATE_TITLE_ACCOUNT');
                    }
                    foreach ($ledger['entries'] as $other) {
                        if (is_array($other) && ($other['id'] ?? null) !== $entry['id'] && is_array($other['security_identity'] ?? null)
                            && ($other['security_identity']['idTitulo'] ?? null) === $title['idTitulo']
                            && ($other['security_identity']['cCta'] ?? null) === $title['cCta']) {
                            return $this->stop('INVALID', 'SECURITIES_REALIZATION_OVERLAPPING_RAW_REVENUE');
                        }
                    }
                    $seenTitles[$key] = $entry['id'];
                    if (isset($titleIssuers[$title['idTitulo']]) && $titleIssuers[$title['idTitulo']] !== $title['CNPJDevedor']) {
                        return $this->stop('INVALID', 'SECURITIES_REALIZATION_ISIN_ISSUER_CONFLICT');
                    }
                    $titleIssuers[$title['idTitulo']] = $title['CNPJDevedor'];
                    foreach ($qualified['financial_entries'] as $derived) {
                        if (isset($seen[$derived['id']])) {
                            return $this->stop('INVALID', 'SECURITIES_REALIZATION_DERIVED_ID_CONFLICT');
                        }
                        $seen[$derived['id']] = true;
                        $trace[] = $derived;
                    }
                    $revenue = $revenue->plus(DecimalValue::fromString($qualified['revenue']));
                    $deduction = $deduction->plus(DecimalValue::fromString($qualified['deductions']));
                    $realizationBindings[$entry['id']] = $qualified;

                    continue;
                } elseif ($kind === 'PUBLIC_OFFER_DEBT_YIELD') {
                    if (($ledger['cooperative_art271_option'] ?? null) !== false) {
                        return $this->stop('ACTION_REQUIRED', 'ART195_COOPERATIVE_ALLOCATION_PENDING');
                    }
                    $sameDocument = array_filter($ledger['entries'], static fn (mixed $other): bool => is_array($other)
                        && ($other['document_id'] ?? null) === $entry['document_id']);
                    if (count($sameDocument) !== 1) {
                        return $this->stop('INVALID', 'PUBLIC_OFFER_DUPLICATE_DOCUMENT_OR_REVENUE');
                    }
                    $publicDebt = (new FinancialPublicOfferDebtQualifier)->resolve($entry, $ledger, $knownAt, $rule['public_offer_debt'] ?? []);
                    if ($publicDebt['status'] !== 'RESOLVED') {
                        return $publicDebt;
                    }
                    $key = $publicDebt['xml_title']['cCta'].'|'.$publicDebt['xml_title']['idTitulo'];
                    if (isset($seenTitles[$key]) && ($publicDebt['sale_recognition_binding']['companion_parent_id'] ?? null) !== $seenTitles[$key]) {
                        return $this->stop('INVALID', 'PUBLIC_OFFER_DUPLICATE_TITLE_ACCOUNT');
                    }
                    $seenTitles[$key] = $entry['id'];
                    $isin = $publicDebt['xml_title']['idTitulo'];
                    $issuer = $publicDebt['xml_title']['CNPJDevedor'];
                    if (isset($titleIssuers[$isin]) && $titleIssuers[$isin] !== $issuer) {
                        return $this->stop('INVALID', 'PUBLIC_OFFER_ISIN_ISSUER_CONFLICT');
                    }
                    $titleIssuers[$isin] = $issuer;
                    $amount = DecimalValue::fromString($publicDebt['taxable_amount']);
                    $excluded = $excluded->plus(DecimalValue::fromString($publicDebt['excluded_positive_amount']));
                    $article = 'LC214 art195 caput; paragraphs1 I/2; DeRE D2101';
                } elseif (in_array($kind, ['FINANCIAL_REVENUE', 'DERIVATIVE_INCOME', 'FAIR_VALUE_REALIZED', 'DEBT_CHARGE_REVERSAL'], true)) {
                    if ($family !== 'CREDIT_FX_SECURITIES_ART192') {
                        return $this->stop('INVALID', 'ENTRY_FAMILY_CONFLICT');
                    }
                    if ($kind === 'DEBT_CHARGE_REVERSAL' && ($entry['previously_deducted'] ?? null) !== true) {
                        return $this->stop('ACTION_REQUIRED', 'PREVIOUS_DEDUCTION_UNPROVEN');
                    }
                    $article = match ($kind) {
                        'DERIVATIVE_INCOME' => 'LC214 art192 paragraph7',
                        'FAIR_VALUE_REALIZED' => 'LC214 art192 paragraph6',
                        'DEBT_CHARGE_REVERSAL' => 'LC214 art192 paragraph3',
                        default => 'LC214 art185; art192 caput',
                    };
                } elseif ($kind === 'SECURITIZATION_DISCOUNT') {
                    if ($family !== 'SECURITIZATION_FACTORING_ART193') {
                        return $this->stop('INVALID', 'ENTRY_FAMILY_CONFLICT');
                    }
                    $article = 'LC214 art193 paragraph1';
                } elseif ($kind === 'SECURITIES_DISPOSAL') {
                    if ($family !== 'CREDIT_FX_SECURITIES_ART192') {
                        return $this->stop('INVALID', 'ENTRY_FAMILY_CONFLICT');
                    }
                    $amount = $amount->minus($this->money($entry['acquisition_cost'] ?? null));
                    $article = 'LC214 art192 paragraph1 II';
                } elseif ($kind === 'REVERSAL_OR_RECOVERY') {
                    if (! is_bool($entry['previously_deducted'] ?? null)) {
                        return $this->stop('ACTION_REQUIRED', 'PREVIOUS_DEDUCTION_UNPROVEN');
                    }
                    $bucket = $entry['previously_deducted'] ? 'REVENUE' : 'EXCLUDED';
                    $article = 'LC214 art186';
                } elseif (in_array($kind, ['CREDIT_PRINCIPAL', 'FUNDING_PRINCIPAL'], true)) {
                    if ($family !== 'CREDIT_FX_SECURITIES_ART192') {
                        return $this->stop('INVALID', 'ENTRY_FAMILY_CONFLICT');
                    }
                    $bucket = 'EXCLUDED';
                    $article = 'LC214 art192 paragraphs1 I/2';
                } elseif ($kind === 'FAIR_VALUE_UNREALIZED') {
                    if ($family !== 'CREDIT_FX_SECURITIES_ART192' || ! $this->proof($entry['subaccount_evidence_id'] ?? null)
                        || ($entry['exceeds_produced_return'] ?? null) !== true) {
                        return $this->stop('ACTION_REQUIRED', 'FAIR_VALUE_DEFERRAL_UNPROVEN');
                    }
                    $bucket = 'DEFERRED';
                    $article = 'LC214 art192 paragraph6';
                    if (array_key_exists('fair_value_nature', $entry)) {
                        if (($rule['deferred_fair_value_sign_policy'] ?? null) !== 'INDEPENDENT_SIGNED_SUBACCOUNT_RECOGNITION'
                            || ! in_array($entry['fair_value_nature'], ['C', 'D'], true) || ! is_array($entry['security_identity'] ?? null)) {
                            return $this->stop('ACTION_REQUIRED', 'FAIR_VALUE_SIGN_POLICY_OR_IDENTITY_UNPROVEN');
                        }
                        $signValue = ['supplier_id' => $ledger['supplier_id'], 'period_start' => $start, 'period_end' => $end,
                            'financial_entry_id' => $entry['id'], 'document_id' => $entry['document_id'], 'amount' => $entry['amount'],
                            'fair_value_nature' => $entry['fair_value_nature'], 'security_identity' => $entry['security_identity'],
                            'subaccount_evidence_id' => $entry['subaccount_evidence_id'], 'exceeds_produced_return' => true];
                        if (! (new FinancialPublicOfferDebtQualifier)->proof($entry['fair_value_sign_evidence'] ?? null, $signValue,
                            $ledger['supplier_id'], $start, $end, $knownAt)) {
                            return $this->stop('ACTION_REQUIRED', 'INDEPENDENT_FAIR_VALUE_SIGN_UNPROVEN');
                        }
                        $deferredSignedAmount = $entry['fair_value_nature'] === 'D' ? $amount->multipliedBy(DecimalValue::fromString('-1')) : $amount;
                    }
                } else {
                    $allowed = $family === 'CREDIT_FX_SECURITIES_ART192'
                        ? ['FUNDING_EXPENSE', 'FX_EXPENSE', 'SECURITIES_LOSS', 'DEBT_INSTRUMENT_CHARGE', 'CREDIT_LOSS', 'ADVISOR_EXPENSE', 'DERIVATIVE_EXPENSE', 'FAIR_VALUE_REALIZED_EXPENSE']
                        : ['FUNDING_EXPENSE', 'SECURITIZATION_COST', 'CREDIT_LOSS'];
                    if (! in_array($kind, $allowed, true)) {
                        return $this->stop('INVALID', 'DEDUCTION_NOT_AUTHORIZED:'.(string) $kind);
                    }
                    foreach (['operation_authorized', 'within_operational_limits', 'not_administrative_expense'] as $key) {
                        if (($entry[$key] ?? null) !== true) {
                            return $this->stop('INVALID', 'DEDUCTION_CONDITION_NOT_MET:'.$key);
                        }
                    }
                    if (! $this->proof($entry['authorization_evidence_id'] ?? null)) {
                        return $this->stop('ACTION_REQUIRED', 'DEDUCTION_AUTHORIZATION_UNPROVEN');
                    }
                    if (in_array($kind, ['ADVISOR_EXPENSE', 'SECURITIZATION_COST'], true)
                        && ($entry['not_employee_or_administrator'] ?? null) !== true) {
                        return $this->stop('INVALID', 'EMPLOYEE_OR_ADMINISTRATOR_COST');
                    }
                    if ($kind === 'ADVISOR_EXPENSE' && (! in_array($entry['advisor_category'] ?? null,
                        ['INVESTMENT_ADVISOR', 'SECURITIES_CONSULTANT', 'BCB_REGISTERED_CORRESPONDENT'], true)
                        || ! $this->proof($entry['advisor_registration_evidence_id'] ?? null))) {
                        return $this->stop('ACTION_REQUIRED', 'ADVISOR_REGISTRATION_UNPROVEN');
                    }
                    if ($kind === 'SECURITIZATION_COST' && ! in_array($entry['securitization_cost_category'] ?? null,
                        ['ISSUANCE', 'DISTRIBUTION', 'CUSTODY', 'BOOKKEEPING', 'REGISTRATION', 'DOCUMENT_PREPARATION',
                            'SEPARATE_ESTATE_ADMINISTRATION', 'TRUSTEE', 'COLLECTION_AGENT', 'RATING_AGENT'], true)) {
                        return $this->stop('INVALID', 'SECURITIZATION_COST_NOT_LISTED');
                    }
                    if ($kind === 'DEBT_INSTRUMENT_CHARGE' && ($entry['not_equity_instrument'] ?? null) !== true) {
                        return $this->stop('INVALID', 'EQUITY_INSTRUMENT_CHARGE');
                    }
                    if ($kind === 'CREDIT_LOSS' && (($entry['market_value_qualified'] ?? null) !== true
                        || ($family === 'CREDIT_FX_SECURITIES_ART192'
                            && (($entry['income_tax_deductibility_qualified'] ?? null) !== true || ($entry['credit_loss_entity_eligible'] ?? null) !== true)))) {
                        return $this->stop('ACTION_REQUIRED', 'CREDIT_LOSS_QUALIFICATION_UNPROVEN');
                    }
                    $bucket = 'DEDUCTION';
                    $article = $family === 'CREDIT_FX_SECURITIES_ART192' ? 'LC214 art187; art192' : 'LC214 art187; art193';
                }
                // Art192 paragraph8 is an operation exclusion, not an elective zero rate.
                if (array_key_exists('cooperative_member_credit_exclusion', $entry) && ! is_bool($entry['cooperative_member_credit_exclusion'])) {
                    return $this->stop('ACTION_REQUIRED', 'COOPERATIVE_FUNDING_EXCLUSION_UNPROVEN');
                }
                if (($entry['cooperative_member_credit_exclusion'] ?? false) === true) {
                    if ($family !== 'CREDIT_FX_SECURITIES_ART192'
                        || ! in_array($entry['funding_origin'] ?? null, ['COOPERATIVE_OR_MEMBER_OWN', 'PUBLIC_DIRECTED_EQUALIZED_OFFICIAL_FUND'], true)
                        || ! $this->proof($entry['cooperative_member_funding_evidence_id'] ?? null)
                        || ! in_array($kind, ['FINANCIAL_REVENUE', 'FUNDING_EXPENSE'], true)) {
                        return $this->stop('INVALID', 'COOPERATIVE_FUNDING_EXCLUSION_CONFLICT');
                    }
                    $bucket = 'EXCLUDED';
                    $article = 'LC214 art192 paragraph8';
                }
                match ($bucket) {
                    'REVENUE' => $revenue = $revenue->plus($amount),
                    'DEDUCTION' => $deduction = $deduction->plus($amount),
                    'DEFERRED' => $deferred = $deferred->plus($deferredSignedAmount ?? $amount),
                    default => $excluded = $excluded->plus($amount),
                };
                $trace[] = [...$entry, 'computed_amount' => $amount->value(), 'bucket' => $bucket, 'source_article' => $article,
                    ...($deferredSignedAmount === null ? [] : ['deferred_signed_amount' => $deferredSignedAmount->value()]),
                    ...($publicDebt === null ? [] : ['fiscal_origin' => 'D2101', 'book_apur_amount' => '0.00', 'd2101_binding' => $publicDebt])];
            }
            $reversal = $zero;
            $proportion = null;
            if (! is_bool($ledger['cooperative_art271_option'] ?? null)) {
                return $this->stop('ACTION_REQUIRED', 'COOPERATIVE_OPTION_UNPROVEN');
            }
            if ($ledger['cooperative_art271_option'] === true) {
                if (! $this->proof($ledger['cooperative_complete_operations_evidence_id'] ?? null)) {
                    return $this->stop('ACTION_REQUIRED', 'COOPERATIVE_PROPORTION_UNPROVEN');
                }
                $all = $this->money($ledger['cooperative_all_operation_value'] ?? null);
                $reduced = $this->money($ledger['cooperative_zero_operation_value'] ?? null);
                if ($reduced->compareTo($all) > 0 || $all->compareTo($zero) <= 0) {
                    return $this->stop('INVALID', 'COOPERATIVE_PROPORTION_CONFLICT');
                }
                // A single monetary rounding; no intermediate percentage truncation.
                $reversal = $deduction->multipliedBy($reduced)->dividedBy($all, 2);
                $proportion = ['numerator' => $reduced->value(), 'denominator' => $all->value()];
            }
        } catch (InvalidArgumentException) {
            return $this->stop('INVALID', 'AMOUNT_OR_DATE_INVALID');
        }

        return ['status' => 'RESOLVED', 'reason' => 'RTC_FINANCIAL_BASE_QUALIFIED',
            'scope' => 'QUALIFIED_LEDGER_BASE_ONLY_NO_RATE_CREDIT_OR_DOCUMENT', 'source_hash' => $rule['source_hash'],
            'family' => $family, 'supplier_id' => $ledger['supplier_id'], 'period_start' => $start, 'period_end' => $end,
            'revenue' => $revenue->value(), 'deductions_before_art188' => $deduction->value(),
            'art188_reversal' => $reversal->value(), 'art188_proportion' => $proportion,
            'excluded' => $excluded->value(), 'deferred' => $deferred->value(),
            'net_base' => $revenue->minus($deduction)->plus($reversal)->value(), 'entries' => $trace,
            ...($realizationBindings === [] ? [] : ['realization_bindings' => $realizationBindings]),
            'negative_base_carryforward' => 'NOT_ASSESSED_REQUIRES_SPECIFIC_RULE', 'posting_performed' => false];
    }

    private function proof(mixed $value): bool
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
    private function stop(string $status, string $reason): array
    {
        return ['status' => $status, 'reason' => 'RTC_FINANCIAL_BASE_'.$reason];
    }
}
