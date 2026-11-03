<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use DateTimeImmutable;

/**
 * B14: plano local de utilização de créditos de IBS/CBS (4B.5.2), LC 214/2025 (Planalto, 2026-11-03):
 * - art. 47, §1º, I: apropriação segregada; vedada, em qualquer hipótese, a compensação cruzada IBS×CBS;
 * - art. 53, I-III: ordem — saldo vencido de períodos anteriores não inscrito em dívida ativa; débitos do
 *   mesmo período em ordem cronológica; débitos de períodos subsequentes (ou ressarcimento, §1º);
 * - art. 53, §2º: valor nominal, vedada correção monetária;
 * - art. 54: extinção do direito após 5 anos contados do 1º dia do período subsequente ao da apropriação;
 * - art. 55: vedada transferência, salvo sucessão (fusão, cisão, incorporação) com data original preservada.
 * Consumir primeiro o crédito mais antigo é escolha de engenharia (a lei não ordena créditos).
 * O plano não extingue débitos nem comprova compensação nos sistemas oficiais.
 */
final readonly class CreditUtilizationPlannerB14
{
    public const VERSION = 'credit-utilization-planner-b14';

    private const STEPS = ['OVERDUE_PRIOR' => 'LC214 art. 53, I', 'CURRENT_PERIOD' => 'LC214 art. 53, II', 'SUBSEQUENT_PERIOD' => 'LC214 art. 53, III'];

    /**
     * @param  list<array<string, mixed>>  $credits
     * @param  list<array<string, mixed>>  $debts
     * @param  list<array<string, mixed>>  $transfers
     * @return array<string, mixed>
     */
    public function plan(array $credits, array $debts, string $period, bool $refundRequested = false, array $transfers = []): array
    {
        if (preg_match('/\A\d{4}-(0[1-9]|1[0-2])\z/', $period) !== 1) {
            return $this->stop('INVALID', 'B14_PERIOD_INVALID');
        }
        foreach ($transfers as $transfer) {
            if (! in_array($transfer['reason'] ?? null, ['MERGER', 'SPLIT', 'INCORPORATION'], true)) {
                return $this->stop('INVALID', 'B14_CREDIT_TRANSFER_PROHIBITED_ART55');
            }
        }
        $pool = [];
        $expired = [];
        $periodStart = new DateTimeImmutable($period.'-01');
        foreach ($credits as $c) {
            $id = $c['credit_id'] ?? null;
            if (! is_string($id) || trim($id) === '' || isset($pool[$id]) || ! in_array($c['tax'] ?? null, ['IBS', 'CBS'], true)
                || ! $this->money($c['amount'] ?? null) || ! $this->month($c['appropriated_period'] ?? null)
                || array_key_exists('monetary_correction', $c)) {
                return $this->stop('INVALID', array_key_exists('monetary_correction', $c) ? 'B14_MONETARY_CORRECTION_PROHIBITED_ART53_2' : 'B14_CREDIT_INVALID', ['credit_id' => $id]);
            }
            if (($c['origin'] ?? 'OWN') === 'SUCCESSION' && (! is_string($c['predecessor_id'] ?? null) || trim($c['predecessor_id']) === '')) {
                return $this->stop('ACTION_REQUIRED', 'B14_SUCCESSION_PREDECESSOR_REQUIRED', ['credit_id' => $id]);
            }
            $expiresOn = (new DateTimeImmutable($c['appropriated_period'].'-01'))->modify('+1 month')->modify('+5 years');
            if ($c['appropriated_period'] > $period) {
                return $this->stop('INVALID', 'B14_CREDIT_FROM_FUTURE_PERIOD', ['credit_id' => $id]);
            }
            if ($periodStart >= $expiresOn) {
                $expired[] = ['credit_id' => $id, 'tax' => $c['tax'], 'amount' => $c['amount'], 'expired_on' => $expiresOn->format('Y-m-d'), 'basis' => 'LC214 art. 54'];

                continue;
            }
            $pool[$id] = ['credit_id' => $id, 'tax' => (string) $c['tax'], 'period' => (string) $c['appropriated_period'],
                'remaining' => DecimalValue::fromString((string) $c['amount']), 'expires_on' => $expiresOn->format('Y-m-d'),
                // LC214 arts. 452 and 468: ZFM/ALC presumed credits only offset own IBS/CBS; no cash refund.
                'refund_prohibited' => ($c['refund_prohibited'] ?? false) === true];
        }
        $queue = [];
        $excluded = [];
        foreach ($debts as $d) {
            $id = $d['debt_id'] ?? null;
            if (! is_string($id) || trim($id) === '' || isset($queue[$id]) || ! in_array($d['tax'] ?? null, ['IBS', 'CBS'], true)
                || ! $this->money($d['amount'] ?? null) || ! $this->month($d['period'] ?? null)
                || ! is_string($d['fact_date'] ?? null) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $d['fact_date']) !== 1) {
                return $this->stop('INVALID', 'B14_DEBT_INVALID', ['debt_id' => $id]);
            }
            if (($d['active_debt_roll'] ?? false) === true) {
                $excluded[] = ['debt_id' => $id, 'reason' => 'B14_ACTIVE_DEBT_ROLL_NOT_COMPENSABLE_ART53_I'];

                continue;
            }
            $step = match (true) {
                $d['period'] < $period => (($d['overdue'] ?? false) === true ? 'OVERDUE_PRIOR' : null),
                $d['period'] === $period => 'CURRENT_PERIOD',
                default => 'SUBSEQUENT_PERIOD',
            };
            if ($step === null) {
                $excluded[] = ['debt_id' => $id, 'reason' => 'B14_PRIOR_DEBT_NOT_OVERDUE_STATUS_REQUIRED'];

                continue;
            }
            $queue[$id] = ['debt_id' => $id, 'tax' => (string) $d['tax'], 'step' => $step, 'fact_date' => $d['fact_date'],
                'remaining' => DecimalValue::fromString((string) $d['amount'])];
        }
        $order = array_flip(array_keys(self::STEPS));
        uasort($queue, fn (array $a, array $b): int => [$order[$a['step']], $a['fact_date'], $a['debt_id']] <=> [$order[$b['step']], $b['fact_date'], $b['debt_id']]);
        uasort($pool, fn (array $a, array $b): int => [$a['period'], $a['credit_id']] <=> [$b['period'], $b['credit_id']]);
        $zero = DecimalValue::fromString('0');
        $compensations = [];
        $deferredToRefund = [];
        foreach ($queue as $debtId => $debt) {
            if ($debt['step'] === 'SUBSEQUENT_PERIOD' && $refundRequested) {
                continue;
            }
            foreach ($pool as $creditId => $credit) {
                if ($credit['tax'] !== $debt['tax'] || $credit['remaining']->compareTo($zero) <= 0) {
                    continue;
                }
                if ($queue[$debtId]['remaining']->compareTo($zero) <= 0) {
                    break;
                }
                $use = $credit['remaining']->compareTo($queue[$debtId]['remaining']) <= 0 ? $credit['remaining'] : $queue[$debtId]['remaining'];
                $pool[$creditId]['remaining'] = $credit['remaining']->minus($use);
                $queue[$debtId]['remaining'] = $queue[$debtId]['remaining']->minus($use);
                $compensations[] = ['credit_id' => $creditId, 'debt_id' => $debtId, 'tax' => $debt['tax'], 'step' => $debt['step'],
                    'basis' => self::STEPS[$debt['step']], 'amount' => $use->rounded(2)->value()];
            }
        }
        if ($refundRequested) {
            foreach ($pool as $credit) {
                if ($credit['remaining']->compareTo($zero) > 0 && ! $credit['refund_prohibited']) {
                    $deferredToRefund[] = ['credit_id' => $credit['credit_id'], 'tax' => $credit['tax'], 'amount' => $credit['remaining']->rounded(2)->value(),
                        'basis' => 'LC214 art. 53, §1º', 'status' => 'REFUND_REQUEST_NOT_PROCESSED'];
                }
            }
        }
        $result = ['status' => 'RESOLVED', 'reason' => 'B14_UTILIZATION_PLANNED', 'version' => self::VERSION, 'period' => $period,
            'compensations' => $compensations,
            'remaining_credits' => array_values(array_map(fn (array $c): array => ['credit_id' => $c['credit_id'], 'tax' => $c['tax'],
                'remaining' => $c['remaining']->rounded(2)->value(), 'expires_on' => $c['expires_on'],
                ...($c['refund_prohibited'] ? ['refund_prohibited' => true] : [])], $pool)),
            'remaining_debts' => array_values(array_map(fn (array $d): array => ['debt_id' => $d['debt_id'], 'tax' => $d['tax'], 'step' => $d['step'],
                'remaining' => $d['remaining']->rounded(2)->value()], $queue)),
            'expired_credits' => $expired, 'excluded_debts' => $excluded, 'refund' => $deferredToRefund,
            'succession_transfers_allowed' => array_values(array_map(fn (array $t): string => (string) $t['reason'], $transfers)),
            ...$this->limits()];

        return [...$result, 'result_hash' => CanonicalJson::hash($result)];
    }

    private function money(mixed $v): bool
    {
        return is_string($v) && preg_match('/\A\d{1,15}(\.\d{1,2})?\z/', $v) === 1;
    }

    private function month(mixed $v): bool
    {
        return is_string($v) && preg_match('/\A\d{4}-(0[1-9]|1[0-2])\z/', $v) === 1;
    }

    /** @return array<string, bool> */
    private function limits(): array
    {
        return ['cross_tax_compensation' => false, 'monetary_correction_applied' => false, 'debts_extinguished' => false,
            'official_compensation_proven' => false, 'production_ready' => false];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function stop(string $status, string $reason, array $ctx = []): array
    {
        return ['status' => $status, 'reason' => $reason, ...$ctx, ...$this->limits()];
    }
}
