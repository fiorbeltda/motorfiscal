<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\NFSe\DereRounding;
use DateTimeImmutable;

/** Financial gross debits are separate from credits, period balance, ISS and the fiscal document. */
final readonly class FinancialTaxEngine
{
    /** @param array<string, mixed> $financialRateCatalog */
    public function __construct(private TemporalRateCatalog $testRates, private array $financialRateCatalog) {}

    /** @param array<string, mixed> $operation
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    public function resolve(array $operation, LocalDate $on, DateTimeImmutable $knownAt, ?bool $accessoryObligationsFulfilled, ?bool $issIncident, array $rule): array
    {
        if (($rule['strategy'] ?? null) !== 'LC214_FINANCIAL_GROSS_DEBITS_ART189_233_348' || ($rule['status'] ?? null) !== 'ACTIVE'
            || ! isset($rule['published_at'], $rule['valid_from']) || $knownAt < new DateTimeImmutable($rule['published_at'])
            || $on->value() < $rule['valid_from'] || (isset($rule['valid_to']) && $on->value() > $rule['valid_to'])
            || (isset($rule['revoked_at']) && $knownAt >= new DateTimeImmutable($rule['revoked_at']))
            || (isset($rule['superseded_at']) && $knownAt >= new DateTimeImmutable($rule['superseded_at']))
            || ! is_string($rule['source_hash'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $rule['source_hash'])) {
            return $this->stop('ACTION_REQUIRED', 'RULE_NOT_AVAILABLE');
        }
        if (($operation['status'] ?? null) !== 'RESOLVED'
            || ($operation['qualification']['regime'] ?? null) !== 'SPECIFIC_FINANCIAL_ART181'
            || ($operation['base']['status'] ?? null) !== 'RESOLVED') {
            return $this->stop('INVALID', 'QUALIFIED_OPERATION_AND_BASE_REQUIRED');
        }
        $baseResult = $operation['base'];
        if (($baseResult['period_end'] ?? null) !== $on->value()
            || ! is_string($operation['qualification']['supplier_id'] ?? null) || $operation['qualification']['supplier_id'] === ''
            || ! in_array($baseResult['family'] ?? null, ['CREDIT_FX_SECURITIES_ART192', 'SECURITIZATION_FACTORING_ART193'], true)
            || ($baseResult['family'] ?? null) !== ($operation['qualification']['base_family'] ?? null)
            || ($baseResult['supplier_id'] ?? null) !== ($operation['qualification']['supplier_id'] ?? null)
            || ! is_string($baseResult['net_base'] ?? null) || ! preg_match('/^-?\d+(?:\.\d{1,2})?$/', $baseResult['net_base'])) {
            return $this->stop('INVALID', 'BASE_SCOPE_OR_PERIOD_CONFLICT');
        }
        $base = DecimalValue::fromString($baseResult['net_base']);
        if ($base->isNegative()) {
            return $this->stop('ACTION_REQUIRED', 'NEGATIVE_PERIOD_BASE_RULE_REQUIRED');
        }
        if (($baseResult['art188_proportion']['numerator'] ?? '0.00') !== '0.00') {
            return $this->stop('ACTION_REQUIRED', 'COOPERATIVE_MIXED_RATE_ALLOCATION_REQUIRED');
        }
        $year = (int) substr($on->value(), 0, 4);
        $sum = $reduction = null;
        if ($year < 2026) {
            return $this->stop('INVALID', 'BEFORE_LEGAL_EFFECT');
        }
        if ($year === 2026 && $accessoryObligationsFulfilled === null) {
            return $this->stop('ACTION_REQUIRED', '2026_COLLECTION_CONDITION_UNKNOWN');
        }
        if ($year >= 2027) {
            if ($issIncident === null) {
                return $this->stop('ACTION_REQUIRED', 'ISS_INCIDENCE_REQUIRED_FOR_COMBINED_RATE');
            }
            $sum = match ($year) {
                2027, 2028 => '10.85', 2029 => '11.00', 2030 => '11.15', 2031 => '11.30',
                2032 => '11.50', default => '12.50',
            };
            $reduction = $issIncident ? match ($year) {
                2027, 2028 => '2.00', 2029 => '1.80', 2030 => '1.60', 2031 => '1.40', 2032 => '1.20', default => '0.00',
            } : '0.00';
        }
        $financialRows = array_values(array_filter($this->financialRateCatalog['rates'] ?? [],
            static fn (array $row): bool => ($row['iss_incident'] ?? null) === $issIncident));
        $catalog = $year === 2026 ? $this->testRates : new TemporalRateCatalog([...$this->financialRateCatalog, 'rates' => $financialRows]);
        $components = [];
        $combined = DecimalValue::fromString('0.00');
        foreach (['IBS_STATE', 'IBS_MUNICIPAL', 'CBS'] as $index => $tax) {
            $resolved = $catalog->resolve($tax, 'BR', $on, $knownAt);
            if ($resolved['status'] !== 'RESOLVED' || ! isset($resolved['row'])) {
                return $this->stop($resolved['status'] === 'CONFLICT' ? 'INVALID' : 'ACTION_REQUIRED',
                    'RATE_NOT_PROVEN:'.$tax, ['combined_rate_percent' => $sum, 'iss_reduction_pp' => $reduction]);
            }
            $row = $resolved['row'];
            if (! is_string($row['source_hash'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $row['source_hash'])) {
                return $this->stop('INVALID', 'RATE_SOURCE_HASH_INVALID:'.$tax);
            }
            $percent = DecimalValue::fromString((string) $row['percent']);
            if ($percent->isNegative() || $percent->compareTo(DecimalValue::fromString('100.00')) > 0) {
                return $this->stop('INVALID', 'RATE_PERCENT_INVALID:'.$tax);
            }
            if ($year === 2026 && $percent->compareTo(DecimalValue::fromString(['0.10', '0.00', '0.90'][$index])) !== 0) {
                return $this->stop('INVALID', '2026_STATUTORY_RATE_CONFLICT:'.$tax);
            }
            if ($year >= 2027 && (($row['regime'] ?? null) !== 'FINANCIAL_ART189'
                || ($row['iss_incident'] ?? null) !== $issIncident
                || ($row['source_act_type'] ?? null) !== ($tax === 'CBS' ? 'UNION_FINANCIAL_RATE_ACT' : 'CGIBS_FINANCIAL_RATE_ACT'))) {
                return $this->stop('ACTION_REQUIRED', 'FINANCIAL_RATE_ACT_NOT_PROVEN:'.$tax);
            }
            $dereRounding = ($rule['rounding_contract'] ?? null) === 'DERE_HALF_EVEN_8_2';
            if ($dereRounding && (! is_string($rule['rounding_source_hash'] ?? null) || ! preg_match('/^[a-f0-9]{64}$/', $rule['rounding_source_hash']))) {
                return $this->stop('ACTION_REQUIRED', 'ROUNDING_SOURCE_UNPROVEN');
            }
            $amount = $dereRounding ? (new DereRounding)->taxAmount($base->value(), $percent->value())
                : $base->multipliedBy($percent)->dividedBy(DecimalValue::fromString('100'), 2)->value();
            $components[$tax] = ['percent' => $percent->value(), 'nominal_amount' => $amount,
                'source_id' => $row['source_id'], 'source_hash' => $row['source_hash']];
            $combined = $combined->plus($percent);
        }
        if ($sum !== null && $combined->compareTo(DecimalValue::fromString($sum)->minus(DecimalValue::fromString($reduction))) !== 0) {
            return $this->stop('INVALID', 'PUBLISHED_COMPONENTS_COMBINED_RATE_CONFLICT');
        }
        $ibs = DecimalValue::fromString($components['IBS_STATE']['nominal_amount'])
            ->plus(DecimalValue::fromString($components['IBS_MUNICIPAL']['nominal_amount']));
        $dispensed = $year === 2026 && $accessoryObligationsFulfilled === true;

        return ['status' => 'RESOLVED', 'reason' => 'RTC_FINANCIAL_GROSS_DEBITS_CALCULATED',
            'scope' => 'GROSS_DEBITS_BEFORE_CREDITS_PERIOD_SETTLEMENT_AND_DOCUMENT', 'period_end' => $on->value(),
            'supplier_id' => $operation['qualification']['supplier_id'], 'base' => $base->rounded(2)->value(),
            'components' => $components, 'ibs_nominal' => $ibs->value(), 'cbs_nominal' => $components['CBS']['nominal_amount'],
            'combined_effective_rate_percent' => $combined->value(), 'article233_combined_rate_percent' => $sum, 'iss_reduction_pp' => $reduction,
            'collection_before_period_credit_offset' => ['IBS' => $dispensed ? '0.00' : $ibs->value(),
                'CBS' => $dispensed ? '0.00' : $components['CBS']['nominal_amount']],
            'collection_status' => $dispensed ? 'DISPENSED_ART348_PARAGRAPH1' : 'GROSS_DEBIT_BEFORE_PERIOD_SETTLEMENT',
            'legacy_pis_cofins' => $year === 2026 ? 'FULL_LEGACY_OBLIGATION_INDEPENDENT_ART348_PARAGRAPH2' : 'SEE_TRANSITION_CONTRACT',
            'source_hash' => $rule['source_hash'], 'rounding' => $dereRounding ? 'DERE_HALF_EVEN_8_2' : 'HALF_UP_2_PER_COMPONENT',
            'payment_performed' => false, 'credit_granted' => false, 'document_approved' => false];
    }

    /** @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function stop(string $status, string $reason, array $context = []): array
    {
        return ['status' => $status, 'reason' => 'RTC_FINANCIAL_TAX_'.$reason, ...$context];
    }
}
