<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use InvalidArgumentException;

/**
 * B40 (4B.4.10): period assessment of the cash-basis specific regimes whose per-operation NFS-e is informational (CST 820):
 * - HEALTH_PLAN (LC214 arts. 234-237): base = premiums received + reserve financial income - claims paid - cancellations/
 *   refunds - intermediation paid - administration fees (art. 235); rates = each sphere's reference rate reduced by 60% (art. 237);
 *   per-operation document cClassTrib 820001;
 * - PET_HEALTH_PLAN (art. 243): same base; rates = reference rates reduced by 30%; document cClassTrib 820003;
 * - BETTING (arts. 244-246): base = gross collection - prizes paid - legal allocations (art. 245); rates = reference rates
 *   (art. 246); document cClassTrib 820004.
 * Acquirers/bettors never get credit (arts. 238, 243, 247). Reference rates are inputs with their source; nothing is estimated.
 * Art. 235, §§2º, 4º and 6º are enforced (ceded co-responsibility deducted; financial income only when liquidated and linked).
 * Arts. 234-247 give no treatment to a negative period base, so it is refused, not carried forward. Funeral plans (art. 236)
 * follow the health-plan rules but have no own document cClassTrib in the catalogue and stay outside this class.
 */
final readonly class PeriodRegimeAssessmentB40
{
    public const CONTRACT = 'RTC-PERIOD-SPECIFIC-REGIME-B40-V1';

    public const SOURCE_HASH = HospitalityRegimeB35::SOURCE_SHA256;

    public const REGIMES = [
        'HEALTH_PLAN' => ['reduction' => '60', 'document_cclasstrib' => '820001', 'articles' => 'LC214 arts. 234-238',
            'lines' => ['PREMIUM_RECEIVED' => ['PLUS', 'art. 235, I, a'], 'RESERVE_FINANCIAL_INCOME' => ['PLUS', 'art. 235, I, b'],
                'CLAIMS_PAID' => ['MINUS', 'art. 235, II, a'], 'PREMIUM_CANCELLATION_REFUND' => ['MINUS', 'art. 235, II, b'],
                'INTERMEDIATION_PAID' => ['MINUS', 'art. 235, II, c'], 'ADMINISTRATION_FEE_PAID' => ['MINUS', 'art. 235, II, d'],
                'CEDED_CORESPONSIBILITY' => ['MINUS', 'art. 235, §2º']]],
        'PET_HEALTH_PLAN' => ['reduction' => '30', 'document_cclasstrib' => '820003', 'articles' => 'LC214 art. 243',
            'lines' => ['PREMIUM_RECEIVED' => ['PLUS', 'art. 235, I, a c/c 243'], 'RESERVE_FINANCIAL_INCOME' => ['PLUS', 'art. 235, I, b c/c 243'],
                'CLAIMS_PAID' => ['MINUS', 'art. 235, II, a c/c 243'], 'PREMIUM_CANCELLATION_REFUND' => ['MINUS', 'art. 235, II, b c/c 243'],
                'INTERMEDIATION_PAID' => ['MINUS', 'art. 235, II, c c/c 243'], 'ADMINISTRATION_FEE_PAID' => ['MINUS', 'art. 235, II, d c/c 243'],
                'CEDED_CORESPONSIBILITY' => ['MINUS', 'art. 235, §2º c/c 243']]],
        'BETTING' => ['reduction' => '0', 'document_cclasstrib' => '820004', 'articles' => 'LC214 arts. 244-247',
            'lines' => ['GROSS_COLLECTION' => ['PLUS', 'art. 245, caput'], 'PRIZES_PAID' => ['MINUS', 'art. 245, I'],
                'LEGAL_ALLOCATIONS' => ['MINUS', 'art. 245, II']]],
    ];

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function assess(array $input): array
    {
        try {
            return $this->calculate($input);
        } catch (InvalidArgumentException $exception) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => $exception->getMessage(), 'posted' => false, 'official' => false];
        }
    }

    /** @param array<string, mixed> $report */
    public function replay(array $report): bool
    {
        return is_array($report['input'] ?? null) && CanonicalJson::hash($this->assess($report['input'])) === CanonicalJson::hash($report);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function calculate(array $input): array
    {
        $this->require(($input['contract'] ?? null) === self::CONTRACT && ($input['currency'] ?? null) === 'BRL', 'B40_CONTRACT_BRL_REQUIRED');
        $regime = $input['regime'] ?? null;
        $this->require(is_string($regime) && isset(self::REGIMES[$regime]), 'B40_REGIME_INVALID');
        $spec = self::REGIMES[$regime];
        $period = $input['period'] ?? null;
        $this->require(is_string($period) && preg_match('/^(20(2[6-9]|3[0-3]))-(0[1-9]|1[0-2])$/D', $period) === 1, 'B40_PERIOD_INVALID');
        $this->require(in_array($input['source_kind'] ?? null, ['SYNTHETIC_TEST', 'DOCUMENTARY_INPUT'], true), 'B40_PROVENANCE_REQUIRED');
        foreach (['taxpayer_id', 'evidence_id'] as $key) {
            $this->require($this->identifier($input[$key] ?? null), 'B40_IDENTITY_REQUIRED:'.$key);
        }
        $rates = $input['reference_rates'] ?? null;
        $this->require(is_array($rates) && $this->identifier($rates['source_id'] ?? null)
            && is_string($rates['source_hash'] ?? null) && preg_match('/^[a-f0-9]{64}$/D', $rates['source_hash']) === 1, 'B40_REFERENCE_RATE_SOURCE_REQUIRED');
        $reference = [];
        foreach (['cbs' => 'cbs_percent', 'ibs_state' => 'ibs_state_percent', 'ibs_municipal' => 'ibs_municipal_percent'] as $key => $field) {
            $value = $rates[$field] ?? null;
            $this->require(is_string($value) && preg_match('/^(?:0|[1-9][0-9]?)\.[0-9]{2,4}$/D', $value) === 1, 'B40_REFERENCE_RATE_INVALID:'.$field);
            $reference[$key] = $value;
        }
        $lines = $input['lines'] ?? null;
        $this->require(is_array($lines) && array_is_list($lines) && $lines !== [], 'B40_LINES_REQUIRED');
        $base = DecimalValue::fromString('0.00');
        $seen = [];
        $report = [];
        foreach ($lines as $line) {
            $this->require(is_array($line) && $this->identifier($line['id'] ?? null) && ! isset($seen[$line['id']])
                && $this->identifier($line['evidence_id'] ?? null), 'B40_LINE_IDENTITY_OR_EVIDENCE_INVALID');
            $seen[$line['id']] = true;
            $kind = $line['kind'] ?? null;
            $this->require(is_string($kind) && isset($spec['lines'][$kind]), 'B40_LINE_KIND_NOT_IN_REGIME:'.(is_string($kind) ? $kind : '?'));
            $amount = $line['amount'] ?? null;
            $this->require(is_string($amount) && preg_match('/^(?:0|[1-9][0-9]*)\.[0-9]{2}$/D', $amount) === 1, 'B40_LINE_AMOUNT_INVALID');
            $cash = $line['cash_date'] ?? null;
            // Cash basis (arts. 235 and 245): only amounts effectively received or paid inside the period count.
            $this->require(is_string($cash) && str_starts_with($cash, $period.'-') && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $cash) === 1,
                'B40_LINE_OUTSIDE_PERIOD_CASH_BASIS');
            if ($kind === 'RESERVE_FINANCIAL_INCOME') {
                // Art. 235, §4º (effectively liquidated: asset redeemed AND technical provisions reduced) and §6º (linked to premiums).
                $this->require(($line['asset_liquidated_or_redeemed'] ?? null) === true && ($line['technical_provisions_reduced'] ?? null) === true
                    && ($line['linked_to_premium_reserves'] ?? null) === true, 'B40_FINANCIAL_INCOME_NOT_EFFECTIVELY_LIQUIDATED_OR_LINKED');
            }
            [$effect, $article] = $spec['lines'][$kind];
            $base = $effect === 'PLUS' ? $base->plus(DecimalValue::fromString($amount)) : $base->minus(DecimalValue::fromString($amount));
            $report[] = ['id' => $line['id'], 'kind' => $kind, 'effect' => $effect, 'amount' => $amount, 'source_article' => 'LC214 '.$article];
        }
        $this->require(! $base->isNegative(), 'B40_NEGATIVE_PERIOD_BASE_TREATMENT_NOT_MATERIALIZED');
        $factor = DecimalValue::fromString('100')->minus(DecimalValue::fromString($spec['reduction']))->dividedBy(DecimalValue::fromString('100'));
        $taxes = [];
        foreach ($reference as $key => $percent) {
            $rate = DecimalValue::fromString($percent)->multipliedBy($factor);
            $taxes[$key] = ['reference_percent' => $percent, 'applied_percent' => $rate->rounded(4)->value(),
                'amount' => $base->multipliedBy($rate)->dividedBy(DecimalValue::fromString('100'))->rounded(2)->value()];
        }
        $result = ['status' => 'LOCAL_PERIOD_REGIME_ASSESSED', 'contract' => self::CONTRACT, 'input' => $input, 'regime' => $regime,
            'period' => $period, 'source_articles' => $spec['articles'], 'source_hash' => self::SOURCE_HASH, 'lines' => $report,
            'taxable_base' => $base->rounded(2)->value(), 'rate_reduction_percent' => $spec['reduction'].'.00', 'taxes' => $taxes,
            'ibs_total' => DecimalValue::fromString($taxes['ibs_state']['amount'])->plus(DecimalValue::fromString($taxes['ibs_municipal']['amount']))->rounded(2)->value(),
            'acquirer_credit' => 'PROHIBITED', 'per_operation_document' => ['cst' => '820', 'cclasstrib' => $spec['document_cclasstrib'],
                'nature' => 'INFORMATIONAL_TAX_DECLARED_IN_SPECIFIC_REGIME'],
            'rounding' => 'HALF_UP_2_PER_COMPONENT', 'official' => false, 'posted' => false, 'payment_performed' => false];

        return [...$result, 'report_hash' => CanonicalJson::hash($result)];
    }

    private function identifier(mixed $value): bool
    {
        return is_string($value) && $value !== '' && trim($value) === $value && preg_match('/[\x00-\x1f\x7f]/', $value) !== 1;
    }

    private function require(bool $condition, string $reason): void
    {
        if (! $condition) {
            throw new InvalidArgumentException($reason);
        }
    }
}
