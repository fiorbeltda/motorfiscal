<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\NFSe\DereRounding;
use DateTimeImmutable;
use InvalidArgumentException;

/** Qualifies art195 income and its unique D2101 fiscal origin, independently of PGCC reception. */
final readonly class FinancialPublicOfferDebtQualifier
{
    public const CONTRACT = 'LC214-ART195-CONSTANT-COST-QUALIFIED-2026.10.06-1';

    public const DAILY_CONTRACT = 'LC214-ART195-DAILY-MEMORY-QUALIFIED-2026.10.06-1';

    /** @param array<string, mixed> $entry
     * @param  array<string, mixed>  $ledger
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    public function resolve(array $entry, array $ledger, DateTimeImmutable $knownAt, array $rule): array
    {
        if (! in_array($rule['contract'] ?? null, [self::CONTRACT, self::DAILY_CONTRACT], true) || ($rule['status'] ?? null) !== 'ACTIVE'
            || ! is_string($rule['published_at'] ?? null) || new DateTimeImmutable($rule['published_at']) > $knownAt
            || ! $this->hash($rule['source_hash'] ?? null) || ! is_array($rule['calculation'] ?? null)
            || ($rule['calculation']['rounding_contract'] ?? null) !== 'DERE_HALF_EVEN_8_2') {
            return $this->stop('RULE_UNAVAILABLE');
        }
        $detail = $entry['public_offer_debt'] ?? null;
        $title = $detail['title'] ?? null;
        $position = $detail['position'] ?? null;
        if (! is_array($detail) || ! is_array($title) || ! is_array($position)
            || ($entry['kind'] ?? null) !== 'PUBLIC_OFFER_DEBT_YIELD'
            || ($ledger['family'] ?? null) !== 'CREDIT_FX_SECURITIES_ART192'
            || ($entry['supplier_id'] ?? null) !== ($ledger['supplier_id'] ?? null)
            || ($position['supplier_id'] ?? null) !== $entry['supplier_id']
            || ($position['period'] ?? null) !== substr((string) ($ledger['period_end'] ?? ''), 0, 7)
            || ($position['idTitulo'] ?? null) !== ($title['idTitulo'] ?? null) || ! $this->isin($title['idTitulo'] ?? null)
            || ($position['cCta'] ?? null) !== ($title['cCta'] ?? null) || ! $this->cnpj($title['CNPJDevedor'] ?? null)
            || ! is_string($detail['result_account_code'] ?? null) || preg_match('/^[A-Za-z0-9]{1,53}$/', $detail['result_account_code']) !== 1
            || $detail['result_account_code'] === ($title['cCta'] ?? null)) {
            return $this->stop('TITLE_POSITION_OR_ENTRY_SCOPE_CONFLICT');
        }
        if (($title['instrument_type'] ?? null) !== 'CORPORATE_DEBT_PUBLIC_OFFER' || ($title['public_offer_regulator'] ?? null) !== 'CVM'
            || ($title['currency'] ?? null) !== 'BRL' || ($title['issuer_regime'] ?? null) !== 'REGULAR'
            || $title['CNPJDevedor'] === $entry['supplier_id']
            || ($title['issuer_in_financial_specific_regime'] ?? null) !== false
            || ($title['creditor_in_financial_specific_regime'] ?? null) !== true) {
            return $this->stop('ART195_PARTIES_OR_OFFER_UNPROVEN');
        }
        try {
            $daily = ($detail['position_mode'] ?? null) === 'DAILY_MEMORY';
            if (isset($detail['position_mode']) && ! $daily) {
                return $this->stop('POSITION_MODE_CONFLICT');
            }
            if ($daily && (($rule['contract'] ?? null) !== self::DAILY_CONTRACT || ! is_array($rule['daily_memory_calculation'] ?? null))) {
                return $this->stop('DAILY_MEMORY_POLICY_UNAVAILABLE');
            }
            $route = $title['holding_route'] ?? null;
            if ($route === 'EXCLUSIVE_FUND_95') {
                if (($title['exclusive_fund'] ?? null) !== true || ! is_string($title['descFundo'] ?? null)
                    || $title['descFundo'] === '' || trim($title['descFundo']) !== $title['descFundo'] || mb_strlen($title['descFundo']) > 255
                    || ! is_string($title['public_offer_portfolio_percent'] ?? null)
                    || DecimalValue::fromString($title['public_offer_portfolio_percent'])->compareTo(DecimalValue::fromString('95')) < 0
                    || DecimalValue::fromString($title['public_offer_portfolio_percent'])->compareTo(DecimalValue::fromString('100')) > 0) {
                    return $this->stop('EXCLUSIVE_FUND_95_PERCENT_UNPROVEN');
                }
            } elseif ($route !== 'DIRECT' || isset($title['exclusive_fund']) || isset($title['descFundo']) || isset($title['public_offer_portfolio_percent'])) {
                return $this->stop('HOLDING_ROUTE_CONFLICT');
            }
            $proof = $detail['eligibility_evidence'] ?? null;
            $value = ['supplier_id' => $entry['supplier_id'], 'period_end' => $ledger['period_end'], 'title' => $title,
                'financial_entry_id' => $entry['id'], 'document_id' => $entry['document_id'], 'amount' => $entry['amount'],
                'result_account_code' => $detail['result_account_code'], 'position_input_hash' => CanonicalJson::hash($position),
                ...($daily ? ['position_mode' => 'DAILY_MEMORY'] : [])];
            if (! $this->proof($proof, $value, $entry['supplier_id'], $ledger['period_start'], $ledger['period_end'], $knownAt)) {
                return $this->stop('ELIGIBILITY_EVIDENCE_UNPROVEN');
            }
            $policy = $daily ? $rule['daily_memory_calculation'] : $rule['calculation'];
            $calc = $daily ? (new FinancialDebtDailyMemoryCalculator)->calculate($position, $policy['series'], $knownAt, $policy)
                : (new FinancialDebtConstantCostCalculator)->calculate($position, $policy['series'], $knownAt, $policy);
            if ($calc['status'] !== 'RESOLVED') {
                return $calc;
            }
            $amounts = $daily ? $calc['monthly'] : $calc;
            $recognition = null;
            if (array_key_exists('sale_recognition', $detail)) {
                $recognition = $this->saleRecognition($entry, $ledger, $knownAt, $rule, $calc);
                if ($recognition['status'] !== 'RESOLVED') {
                    return $recognition;
                }
            }
            $grossCredit = $grossDebit = DecimalValue::fromString('0.00');
            $grossRounding = null;
            if ($daily) {
                foreach ($calc['daily_memory'] as $day) {
                    foreach ($day['operations'] as $movement) {
                        $accountingGain = DecimalValue::fromString($movement['realized_sale_gain']);
                        $acquisitionDifference = ($movement['kind'] ?? null) === 'SELL'
                            ? DecimalValue::fromString($movement['book_amount'])->plus($accountingGain)->minus(DecimalValue::fromString($movement['acquisition_cost_released']))
                            : DecimalValue::fromString('0.00');
                        if (($accountingGain->compareTo(DecimalValue::fromString('0.00')) !== 0
                            || $acquisitionDifference->compareTo(DecimalValue::fromString('0.00')) !== 0) && $recognition === null) {
                            return $this->stop('DAILY_SALE_GAIN_TAX_TREATMENT_PENDING');
                        }
                    }
                    $appropriation = DecimalValue::fromString($day['appropriation']);
                    if ($appropriation->isNegative()) {
                        $grossDebit = $grossDebit->minus($appropriation);
                    } else {
                        $grossCredit = $grossCredit->plus($appropriation);
                    }
                }
                $round = new DereRounding;
                $grossCredit = DecimalValue::fromString($round->finalAmount($grossCredit->value()));
                $grossDebit = DecimalValue::fromString($round->finalAmount($grossDebit->value()));
                if ($grossCredit->minus($grossDebit)->compareTo(DecimalValue::fromString($amounts['vVarMensal'])) !== 0) {
                    if (! array_key_exists('gross_rounding_reconciliation', $detail) || ! is_string($recognition['companion_parent_id'] ?? null)) {
                        return $this->stop('DAILY_GROSS_ROUNDING_RECONCILIATION_PENDING');
                    }
                }
                if (array_key_exists('gross_rounding_reconciliation', $detail)) {
                    if (! is_string($recognition['companion_parent_id'] ?? null)) {
                        return $this->stop('DAILY_GROSS_ROUNDING_RECONCILIATION_PENDING');
                    }
                    $grossRounding = (new FinancialPublicGrossRoundingQualifier)->resolve($detail['gross_rounding_reconciliation'], $calc, $title,
                        $entry['supplier_id'], $detail['result_account_code'], $entry['id'], $recognition['companion_parent_id'], $knownAt, $rule);
                    if ($grossRounding['status'] !== 'RESOLVED') {
                        return $grossRounding;
                    }
                    $grossCredit = DecimalValue::fromString($grossRounding['adjusted_gross_credit']);
                    $grossDebit = DecimalValue::fromString($grossRounding['adjusted_gross_debit']);
                }
            }
            $variation = DecimalValue::fromString($amounts['vVarMensal']);
            $absolute = $variation->isNegative() ? $variation->multipliedBy(DecimalValue::fromString('-1')) : $variation;
            if (! is_string($entry['amount'] ?? null) || DecimalValue::fromString($entry['amount'])->compareTo($absolute) !== 0) {
                return $this->stop('GROSS_YIELD_AMOUNT_CONFLICT');
            }
            $received = $this->money($title['vJurosRec'] ?? null);
            if ($daily && $received->compareTo(DecimalValue::fromString($amounts['vJurosRec'])) !== 0) {
                return $this->stop('DAILY_COUPON_AMOUNT_CONFLICT');
            }
            $closing = $daily ? DecimalValue::fromString($amounts['vSaldoContFin']) : DecimalValue::fromString($calc['cost_basis'])->plus($variation)->minus($received);
            if ($closing->isNegative()) {
                return $this->stop('TITLE_CLOSING_BALANCE_NEGATIVE');
            }
            $grossPositive = $variation->isNegative() ? DecimalValue::fromString('0.00') : $variation;
            $base = DecimalValue::fromString($amounts['vApur']);
            $excess = $daily ? $grossPositive->minus(DecimalValue::fromString($amounts['vSelic'])) : DecimalValue::fromString($calc['excess_above_selic']);
            $excess = $excess->isNegative() ? DecimalValue::fromString('0.00') : $excess;
            $pisApplied = $grossPositive->minus($excess)->minus($base);
            $xmlTitle = ['idTitulo' => $title['idTitulo'], 'cCta' => $title['cCta'],
                ...($route === 'EXCLUSIVE_FUND_95' ? ['descFundo' => $title['descFundo']] : []),
                'CNPJDevedor' => $title['CNPJDevedor'], 'vSaldoContIni' => $daily ? $amounts['vSaldoContIni'] : $calc['cost_basis'],
                'vEntradas' => $daily ? $amounts['vEntradas'] : '0.00', 'vSaidas' => $daily ? $amounts['vSaidas'] : '0.00',
                'vJurosRec' => $received->value(), 'vVarMensal' => $amounts['vVarMensal'], 'vSaldoContFin' => $closing->rounded(2)->value(),
                'vSelic' => $amounts['vSelic'], 'vPisCofins' => $amounts['vPisCofins'], 'vApur' => $amounts['vApur']];

            return ['status' => 'RESOLVED', 'reason' => 'RTC_FINANCIAL_ART195_YIELD_QUALIFIED', 'contract' => $daily ? self::DAILY_CONTRACT : self::CONTRACT,
                'financial_entry_id' => $entry['id'], 'supplier_id' => $entry['supplier_id'], 'period_end' => $ledger['period_end'],
                'tax_origin' => 'D2101', 'book_apur_amount' => '0.00', 'result_account_code' => $detail['result_account_code'],
                'gross_amount' => $absolute->rounded(2)->value(), 'gross_nature' => $variation->isNegative() ? 'D' : 'C',
                'taxable_amount' => $amounts['vApur'], 'excluded_positive_amount' => $grossPositive->minus($base)->rounded(2)->value(),
                'art195_excess' => $excess->rounded(2)->value(), 'pis_deduction_applied' => $pisApplied->rounded(2)->value(),
                'unoffset_title_loss' => ($variation->isNegative() ? $absolute : DecimalValue::fromString('0.00'))->rounded(2)->value(),
                'expected_codTrib' => $route === 'DIRECT' ? '110113001' : '110113002', 'xml_title' => $xmlTitle,
                'calculation' => $calc, 'calculation_hash' => CanonicalJson::hash($calc), 'eligibility_evidence_hash' => $proof['source_hash'],
                ...($recognition === null ? [] : ['sale_recognition_binding' => $recognition]),
                ...($grossRounding === null ? [] : ['gross_rounding_reconciliation' => $grossRounding]),
                ...($daily ? ['position_mode' => 'DAILY_MEMORY', 'first_acquisition' => $position['first_acquisition'],
                    'gross_result_credit' => $grossCredit->value(), 'gross_result_debit' => $grossDebit->value(),
                    'asset_movements' => ['vMovDebt' => DecimalValue::fromString($amounts['vEntradas'])->plus($grossCredit)->value(),
                        'vMovCred' => DecimalValue::fromString($amounts['vSaidas'])->plus($received)->plus($grossDebit)->value()],
                    'normative_method_homologated' => false] : []),
                'source_hash' => $rule['source_hash'], 'production_ready' => false, 'posting_performed' => false];
        } catch (InvalidArgumentException) {
            return $this->stop('AMOUNT_OR_DATE_INVALID');
        }
    }

    /** @param array<string,mixed> $entry
     * @param  array<string,mixed>  $ledger
     * @param  array<string,mixed>  $rule
     * @param  array<string,mixed>  $calculation
     * @return array<string,mixed>
     */
    private function saleRecognition(array $entry, array $ledger, DateTimeImmutable $known, array $rule, array $calculation): array
    {
        $detail = $entry['public_offer_debt'];
        $bridge = $detail['sale_recognition'];
        $policy = $rule['sale_recognition_policy'] ?? null;
        $companionMode = ($policy['mode'] ?? null) === 'PUBLIC_CURVE_D2101_WITH_QUALIFIED_FAIR_VALUE_COMPANION_D1101';
        if (($detail['position_mode'] ?? null) !== 'DAILY_MEMORY' || ! is_array($bridge) || ! is_array($policy)
            || (! $companionMode && ($policy['mode'] ?? null) !== 'REPLAYED_ALREADY_RECOGNIZED_YIELD_DISPOSAL_NO_NEW_RESULT')
            || ! is_array($policy['analysis'] ?? null)
            || ($policy['analysis']['historical_snapshot_required'] ?? null) !== true
            || CanonicalJson::encode($policy['analysis']['daily_memory_calculation'] ?? null) !== CanonicalJson::encode($rule['daily_memory_calculation'] ?? null)) {
            return $this->stop('SALE_RECOGNITION_POLICY_UNAVAILABLE');
        }
        $input = $bridge['analysis_input'] ?? null;
        if (! is_array($input) || CanonicalJson::encode($input['position'] ?? null) !== CanonicalJson::encode($detail['position'])
            || CanonicalJson::encode($input['title'] ?? null) !== CanonicalJson::encode($detail['title'])) {
            return $this->stop('SALE_RECOGNITION_SCOPE_CONFLICT');
        }
        $companionId = $bridge['companion_parent_id'] ?? null;
        if ($companionMode) {
            $matches = array_values(array_filter($ledger['entries'] ?? [], static fn (mixed $other): bool => is_array($other) && ($other['id'] ?? null) === $companionId));
            $raw = count($matches) === 1 ? $matches[0] : null;
            $qualified = is_string($companionId) ? ($ledger['realization_bindings'][$companionId] ?? null) : null;
            $rawValid = is_array($raw) && ($raw['kind'] ?? null) === 'QUALIFIED_SECURITIES_REALIZATION'
                && ($raw['securities_realization']['public_yield_entry_id'] ?? null) === $entry['id']
                && is_array($raw['securities_realization']['analysis_input'] ?? null)
                && CanonicalJson::encode($raw['securities_realization']['analysis_input']) === CanonicalJson::encode($input);
            $qualifiedValid = is_array($qualified) && ($qualified['status'] ?? null) === 'RESOLVED'
                && ($qualified['contract'] ?? null) === FinancialSecuritiesRealizationQualifier::CONTRACT
                && ($qualified['public_yield_entry_id'] ?? null) === $entry['id']
                && ($qualified['analysis']['input_hash'] ?? null) === CanonicalJson::hash($input)
                && ($qualified['analysis']['public_offer_art195'] ?? null) === true;
            if (! is_string($companionId) || $companionId === $entry['id'] || (! $rawValid && ! $qualifiedValid)) {
                return $this->stop('FAIR_VALUE_COMPANION_SCOPE_UNPROVEN');
            }
        } elseif ($companionId !== null) {
            return $this->stop('UNEXPECTED_FAIR_VALUE_COMPANION');
        }
        foreach ($ledger['entries'] ?? [] as $other) {
            if (is_array($other) && ($other['id'] ?? null) !== $entry['id']
                && ($other['security_identity']['idTitulo'] ?? null) === $detail['title']['idTitulo']
                && ($other['security_identity']['cCta'] ?? null) === $detail['title']['cCta']) {
                if ($companionMode && (($rawValid && $other['id'] === $companionId)
                    || ($qualifiedValid && ($other['realization_parent_id'] ?? null) === $companionId
                        && isset(array_column($qualified['financial_entries'], null, 'id')[$other['id']])
                        && CanonicalJson::encode(array_column($qualified['financial_entries'], null, 'id')[$other['id']]) === CanonicalJson::encode($other)))) {
                    continue;
                }

                return $this->stop('SALE_RECOGNITION_SEPARATE_SECURITY_ENTRY_PENDING');
            }
        }
        $analysis = (new FinancialSecuritiesRealizationAnalyzer)->analyze($input, $policy['analysis'], $known);
        if (($analysis['status'] ?? null) !== 'RECONCILED') {
            return $analysis;
        }
        $releaseWithoutSale = ($policy['analysis']['public_other_release_without_sale_policy'] ?? null) === 'REPLAYED_PUBLIC_AMORTIZATION_OR_COUPON_WITHOUT_SALE'
            && ($analysis['public_other_release_without_sale_reconciled'] ?? null) === true;
        if ($analysis['public_offer_art195'] !== true || $analysis['daily_memory_hash'] !== CanonicalJson::hash($calculation)
            || ($analysis['realizations'] === [] && ! $releaseWithoutSale)) {
            return $this->stop('SALE_RECOGNITION_MEMORY_OR_COVERAGE_CONFLICT');
        }
        foreach ($analysis['realizations'] as $sale) {
            foreach ($companionMode ? ['new_produced_return_at_sale', 'rounding_bridge']
                : ['accounting_gain', 'new_produced_return_at_sale', 'deferred_fair_value_realized', 'new_fair_value_change_at_sale', 'rounding_bridge'] as $key) {
                $value = $key === 'accounting_gain' ? $sale[$key] : $sale['components'][$key];
                if ($key === 'rounding_bridge'
                    && ($policy['analysis']['third_sale_book_rounding_policy'] ?? null) === FinancialThirdSaleRoundingQualifier::METHOD
                    && ($analysis['replayed_fiscal_history']['public_created_rounding_closing_replayed'] ?? false) === true
                    && ($sale['book_release_rounding']['method'] ?? null) === FinancialThirdSaleRoundingQualifier::METHOD
                    && ($sale['book_release_rounding']['status'] ?? null) === 'RESOLVED'
                    && DecimalValue::fromString($sale['book_release_rounding']['total_rounding_release'])->compareTo(DecimalValue::fromString($value)) === 0) {
                    continue;
                }
                if ($key === 'rounding_bridge'
                    && ($policy['analysis']['historical_sale_book_rounding_policy'] ?? null) === 'PROVED_HISTORICAL_AVERAGE_RELEASE_WITH_SEPARATE_CREATED_ROUNDING_8_2'
                    && ($sale['book_release_rounding']['method'] ?? null) === 'PROVED_HISTORICAL_AVERAGE_RELEASE_WITH_SEPARATE_CREATED_ROUNDING_8_2'
                    && ($sale['book_release_rounding']['status'] ?? null) === 'RESOLVED'
                    && DecimalValue::fromString($sale['book_release_rounding']['total_rounding_release'])->compareTo(DecimalValue::fromString($value)) === 0) {
                    continue;
                }
                if ($key === 'rounding_bridge' && ($analysis['replayed_fiscal_history']['public_explicit_net_closing_replayed'] ?? false) === true
                    && isset($sale['historical_carrying_rounding'])
                    && DecimalValue::fromString($sale['historical_carrying_rounding'])->compareTo(DecimalValue::fromString($value)) === 0) {
                    continue;
                }
                if ($key === 'rounding_bridge'
                    && ($policy['analysis']['sale_book_rounding_policy'] ?? null) === 'PROVED_AVERAGE_CARRYING_RELEASE_HALF_EVEN_8_2'
                    && ($sale['book_release_rounding']['status'] ?? null) === 'RESOLVED'
                    && DecimalValue::fromString($sale['book_release_rounding']['signed_adjustment'])->compareTo(DecimalValue::fromString($value)) === 0) {
                    continue;
                }
                if (DecimalValue::fromString($value)->compareTo(DecimalValue::fromString('0')) !== 0) {
                    return $this->stop('SALE_NEW_RESULT_OR_FAIR_VALUE_TREATMENT_PENDING');
                }
            }
        }
        foreach (['opening', 'closing'] as $boundary) {
            if (! $companionMode && DecimalValue::fromString($input['recognition_inventory'][$boundary]['deferred_fair_value_realized'])->compareTo(DecimalValue::fromString('0')) !== 0) {
                return $this->stop('SALE_NEW_RESULT_OR_FAIR_VALUE_TREATMENT_PENDING');
            }
        }
        foreach ($input['recognition_inventory']['other_releases'] as $release) {
            if (! $companionMode && DecimalValue::fromString($release['components']['deferred_fair_value_realized'])->compareTo(DecimalValue::fromString('0')) !== 0) {
                return $this->stop('SALE_NEW_RESULT_OR_FAIR_VALUE_TREATMENT_PENDING');
            }
        }
        $value = ['supplier_id' => $entry['supplier_id'], 'period_start' => $ledger['period_start'], 'period_end' => $ledger['period_end'],
            'financial_entry_id' => $entry['id'], 'document_id' => $entry['document_id'], 'analysis_input_hash' => CanonicalJson::hash($input),
            'calculation_hash' => CanonicalJson::hash($calculation), 'policy_hash' => CanonicalJson::hash($policy),
            'appropriation_excludes_fair_value' => true, 'previously_recognized_returns_not_reincluded' => true,
            ...($companionMode ? ['companion_parent_id' => $companionId, 'fair_value_tax_origin' => 'D1101'] : [])];
        if (! $this->proof($bridge['independent_evidence'] ?? null, $value, $entry['supplier_id'], $ledger['period_start'], $ledger['period_end'], $known)) {
            return $this->stop('INDEPENDENT_SALE_RECOGNITION_UNPROVEN');
        }

        return ['status' => 'RESOLVED', 'contract' => 'ART195-ALREADY-RECOGNIZED-YIELD-DISPOSAL-2026.10.07-1',
            'analysis' => $analysis, 'analysis_input_hash' => CanonicalJson::hash($input), 'policy_hash' => CanonicalJson::hash($policy),
            'evidence_hash' => $bridge['independent_evidence']['source_hash'], 'sale_count' => count($analysis['realizations']),
            'prior_return_not_reincluded' => $analysis['component_totals']['prior_produced_return'],
            'current_curve_return_not_reincluded' => $analysis['component_totals']['current_produced_return_already_in_curve'],
            ...($companionMode ? ['companion_parent_id' => $companionId, 'fair_value_tax_origin' => 'D1101', 'fair_value_in_d2101_variation' => false] : []),
            'additional_sale_taxable_amount' => '0.00', 'tax_origin' => 'D2101', 'production_ready' => false, 'posting_performed' => false];
    }

    /** @param array<string, mixed> $value */
    public function proof(mixed $p, array $value, string $supplier, string $start, string $end, DateTimeImmutable $known): bool
    {
        return is_array($p) && ($p['qualified'] ?? null) === true && ($p['supplier_id'] ?? null) === $supplier
            && is_string($p['evidence_id'] ?? null) && trim($p['evidence_id']) !== '' && $this->hash($p['source_hash'] ?? null)
            && $p['source_hash'] === CanonicalJson::hash(array_diff_key($p, ['source_hash' => true]))
            && CanonicalJson::encode($p['value'] ?? null) === CanonicalJson::encode($value)
            && LocalDate::fromString((string) ($p['valid_from'] ?? ''))->value() <= $start
            && LocalDate::fromString((string) ($p['valid_to'] ?? ''))->value() >= $end
            && LocalDate::fromString((string) ($p['observed_on'] ?? ''))->value() >= $end
            && $p['observed_on'] <= $known->format('Y-m-d');
    }

    /** @param array<string,mixed> $title */
    public function validTitleIdentity(array $title): bool
    {
        return $this->isin($title['idTitulo'] ?? null) && $this->cnpj($title['CNPJDevedor'] ?? null)
            && is_string($title['cCta'] ?? null) && preg_match('/^[A-Za-z0-9]{1,53}$/', $title['cCta']) === 1;
    }

    private function money(mixed $v): DecimalValue
    {
        if (! is_string($v) || preg_match('/^(0|[1-9][0-9]{0,14})\.[0-9]{2}$/', $v) !== 1) {
            throw new InvalidArgumentException('Canonical money required.');
        }

        return DecimalValue::fromString($v);
    }

    private function hash(mixed $v): bool
    {
        return is_string($v) && preg_match('/^[a-f0-9]{64}$/', $v) === 1;
    }

    private function isin(mixed $v): bool
    {
        if (! is_string($v) || preg_match('/^[A-Z]{2}[0-9A-Z]{9}[0-9]$/', $v) !== 1) {
            return false;
        }
        $digits = '';
        foreach (str_split($v) as $char) {
            $digits .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }
        $sum = 0;
        foreach (str_split(strrev($digits)) as $i => $char) {
            $n = (int) $char * ($i % 2 === 1 ? 2 : 1);
            $sum += intdiv($n, 10) + $n % 10;
        }

        return $sum % 10 === 0;
    }

    private function cnpj(mixed $v): bool
    {
        if (! is_string($v) || preg_match('/^[0-9A-Z]{12}[0-9]{2}$/', $v) !== 1 || preg_match('/^(.)\1{13}$/', $v) === 1) {
            return false;
        }
        foreach ([[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]] as $weights) {
            $sum = 0;
            foreach ($weights as $i => $weight) {
                $sum += (ord($v[$i]) - 48) * $weight;
            }
            $digit = $sum % 11 < 2 ? 0 : 11 - $sum % 11;
            if ((int) $v[count($weights)] !== $digit) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function stop(string $reason): array
    {
        return ['status' => 'ACTION_REQUIRED', 'reason' => 'RTC_FINANCIAL_ART195_'.$reason, 'production_ready' => false];
    }
}
