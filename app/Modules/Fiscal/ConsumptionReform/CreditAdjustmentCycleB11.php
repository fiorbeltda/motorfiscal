<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use App\Modules\Fiscal\Core\Shared\DecimalValue;

/**
 * B11: ciclo de estorno (REVERSAL) e anulação por apropriação indevida (DISALLOWANCE, a "glosa"
 * de crédito) sobre créditos de IBS/CBS já apropriados.
 *
 * Fontes (LC 214/2025, texto do Planalto obtido em 2026-11-03, SHA-256 0a0d190b…673c):
 * - art. 47, §6º: estorno quando o bem perecer, deteriorar-se ou for roubado, furtado ou extraviado;
 * - art. 47, §7º: ativo imobilizado roubado/furtado — estorno proporcional à vida útil e às taxas de
 *   depreciação definidas em regulamento (parâmetro externo; exigido com fonte);
 * - art. 45, §§1º-2º: ajuste no saldo do período; estorno de crédito apropriado em período anterior
 *   com os acréscimos do art. 29, §§2º-4º, desde a apropriação indevida;
 * - arts. 447, §2º e 465, §2º: estorno de crédito presumido (ZFM/ALC) com condição não comprovada.
 *
 * O ciclo determina registro, saldo e a obrigação de acréscimos; não calcula multa/juros (a série
 * Selic é externa) e nunca declara pagamento, extinção ou homologação.
 */
final readonly class CreditAdjustmentCycleB11
{
    public const VERSION = 'credit-adjustment-cycle-b11';

    /** cause => [kind, legal basis, accrual rule] */
    public const CAUSES = [
        'PERISHED' => ['REVERSAL', 'LC214 art. 47, §6º', 'NONE_IF_REGISTERED_IN_EVENT_PERIOD'],
        'DETERIORATED' => ['REVERSAL', 'LC214 art. 47, §6º', 'NONE_IF_REGISTERED_IN_EVENT_PERIOD'],
        'ROBBERY' => ['REVERSAL', 'LC214 art. 47, §6º', 'NONE_IF_REGISTERED_IN_EVENT_PERIOD'],
        'THEFT' => ['REVERSAL', 'LC214 art. 47, §6º', 'NONE_IF_REGISTERED_IN_EVENT_PERIOD'],
        'LOSS' => ['REVERSAL', 'LC214 art. 47, §6º', 'NONE_IF_REGISTERED_IN_EVENT_PERIOD'],
        'FIXED_ASSET_ROBBERY_OR_THEFT' => ['REVERSAL', 'LC214 art. 47, §7º', 'NONE_IF_REGISTERED_IN_EVENT_PERIOD'],
        'IMPROPER_APPROPRIATION' => ['DISALLOWANCE', 'LC214 art. 45, §2º; art. 29, §§2º-4º', 'FROM_APPROPRIATION_IF_PRIOR_PERIOD'],
        'PRESUMED_CREDIT_CONDITION_UNMET' => ['DISALLOWANCE', 'LC214 art. 447, §2º; art. 465, §2º', 'LEGAL_ADDITIONS_REQUIRED'],
    ];

    /**
     * @param  list<array<string, mixed>>  $credits  appropriated credits
     * @param  list<array<string, mixed>>  $events  adjustment events
     * @return array<string, mixed>
     */
    public function apply(array $credits, array $events, string $period): array
    {
        if (preg_match('/\A\d{4}-(0[1-9]|1[0-2])\z/', $period) !== 1) {
            return $this->stop('INVALID', 'B11_PERIOD_INVALID');
        }
        /** @var array<string, array{credit_id: string, tax: string, document_id: string, kind: string, appropriated_on: string, appropriated: string, adjusted: string, registered_this_period: string}> $ledger */
        $ledger = [];
        foreach ($credits as $credit) {
            $id = $credit['credit_id'] ?? null;
            if (! is_string($id) || trim($id) === '' || isset($ledger[$id])
                || ! in_array($credit['tax'] ?? null, ['IBS', 'CBS'], true)
                || ! is_string($credit['document_id'] ?? null) || trim($credit['document_id']) === ''
                || ! $this->money($credit['amount'] ?? null) || ! $this->date($credit['appropriated_on'] ?? null)
                || substr((string) $credit['appropriated_on'], 0, 7) > $period) {
                return $this->stop('INVALID', 'B11_CREDIT_RECORD_INVALID', ['credit_id' => $id]);
            }
            $ledger[$id] = ['credit_id' => $id, 'tax' => (string) $credit['tax'], 'document_id' => (string) $credit['document_id'],
                'kind' => (string) ($credit['kind'] ?? 'ORDINARY'), 'appropriated_on' => (string) $credit['appropriated_on'],
                'appropriated' => (string) $credit['amount'], 'adjusted' => '0.00', 'registered_this_period' => '0.00'];
        }
        $seen = [];
        $sorted = $events;
        usort($sorted, fn (array $a, array $b): int => [(string) ($a['event_date'] ?? ''), (string) ($a['event_id'] ?? '')]
            <=> [(string) ($b['event_date'] ?? ''), (string) ($b['event_id'] ?? '')]);
        $registered = [];
        $deferred = [];
        $trace = [];
        foreach ($sorted as $event) {
            $eventId = $event['event_id'] ?? null;
            if (! is_string($eventId) || trim($eventId) === '' || isset($seen[$eventId])) {
                return $this->stop('INVALID', 'B11_EVENT_ID_INVALID_OR_DUPLICATE', ['event_id' => $eventId]);
            }
            $seen[$eventId] = true;
            $cause = $event['cause'] ?? null;
            if (! is_string($cause) || ! isset(self::CAUSES[$cause])) {
                return $this->stop('INVALID', 'B11_CAUSE_NOT_IN_LEGAL_CATALOGUE', ['event_id' => $eventId]);
            }
            [$kind, $basis, $accrualRule] = self::CAUSES[$cause];
            if (($event['kind'] ?? null) !== $kind) {
                return $this->stop('INVALID', 'B11_KIND_CAUSE_MISMATCH', ['event_id' => $eventId]);
            }
            $credit = $ledger[$event['credit_id'] ?? ''] ?? null;
            if ($credit === null) {
                return $this->stop('INVALID', 'B11_CREDIT_NOT_FOUND', ['event_id' => $eventId]);
            }
            if (($event['tax'] ?? null) !== $credit['tax'] || ($event['document_id'] ?? null) !== $credit['document_id']) {
                return $this->stop('INVALID', 'B11_CREDIT_LINK_CONFLICT', ['event_id' => $eventId]);
            }
            if (! is_string($event['evidence_id'] ?? null) || trim($event['evidence_id']) === '') {
                return $this->stop('ACTION_REQUIRED', 'B11_EVIDENCE_REQUIRED', ['event_id' => $eventId]);
            }
            if (! $this->date($event['event_date'] ?? null) || $event['event_date'] < $credit['appropriated_on']) {
                return $this->stop('INVALID', 'B11_EVENT_DATE_INVALID', ['event_id' => $eventId]);
            }
            if ($cause === 'PRESUMED_CREDIT_CONDITION_UNMET' && $credit['kind'] !== 'PRESUMED') {
                return $this->stop('INVALID', 'B11_PRESUMED_CAUSE_ON_NON_PRESUMED_CREDIT', ['event_id' => $eventId]);
            }
            $amount = $event['amount'] ?? null;
            if ($cause === 'FIXED_ASSET_ROBBERY_OR_THEFT') {
                $proportion = $event['regulation_proportion'] ?? null;
                $source = $event['regulation_source_id'] ?? null;
                if (! is_string($proportion) || preg_match('/\A(0(\.\d{1,10})?|1(\.0{1,10})?)\z/', $proportion) !== 1
                    || ! is_string($source) || trim($source) === '') {
                    return $this->stop('ACTION_REQUIRED', 'B11_FIXED_ASSET_REGULATION_PARAMETERS_REQUIRED', ['event_id' => $eventId]);
                }
                $remaining = DecimalValue::fromString($credit['appropriated'])->minus(DecimalValue::fromString($credit['adjusted']));
                $amount = DecimalValue::fromString($credit['appropriated'])->multipliedBy(DecimalValue::fromString($proportion))->rounded(2)->value();
                if (DecimalValue::fromString($amount)->compareTo($remaining) > 0) {
                    return $this->stop('INVALID', 'B11_ADJUSTMENT_EXCEEDS_REMAINING', ['event_id' => $eventId]);
                }
            }
            if (! $this->money($amount) || DecimalValue::fromString((string) $amount)->compareTo(DecimalValue::fromString('0')) <= 0) {
                return $this->stop('INVALID', 'B11_AMOUNT_INVALID', ['event_id' => $eventId]);
            }
            $eventPeriod = substr((string) $event['event_date'], 0, 7);
            if ($eventPeriod > $period) {
                $deferred[] = ['event_id' => $eventId, 'reason' => 'B11_EVENT_AFTER_REGISTRATION_PERIOD', 'event_period' => $eventPeriod];

                continue;
            }
            $next = DecimalValue::fromString($credit['adjusted'])->plus(DecimalValue::fromString((string) $amount));
            if ($next->compareTo(DecimalValue::fromString($credit['appropriated'])) > 0) {
                return $this->stop('INVALID', 'B11_ADJUSTMENT_EXCEEDS_REMAINING', ['event_id' => $eventId]);
            }
            $appropriationPeriod = substr((string) $credit['appropriated_on'], 0, 7);
            $accrual = match (true) {
                $accrualRule === 'FROM_APPROPRIATION_IF_PRIOR_PERIOD' && $appropriationPeriod < $period => [
                    'status' => 'LEGAL_ADDITIONS_REQUIRED', 'basis' => 'LC214 art. 45, §2º; art. 29, §§2º-4º',
                    'from_date' => $credit['appropriated_on'], 'components' => ['MULTA_MORA_0_33_DIA_LIMITE_20', 'JUROS_SELIC_MAIS_1_MES_PAGAMENTO'],
                    'computed' => false, 'dependency' => 'SERIE_SELIC_OFICIAL_E_DATA_DE_PAGAMENTO'],
                $accrualRule === 'LEGAL_ADDITIONS_REQUIRED' => [
                    'status' => 'LEGAL_ADDITIONS_REQUIRED', 'basis' => $basis.'; art. 29, §2º',
                    'from_date' => $credit['appropriated_on'], 'components' => ['ACRESCIMOS_LEGAIS_CABIVEIS'],
                    'computed' => false, 'dependency' => 'REGULAMENTO_E_SERIE_SELIC_OFICIAL'],
                $accrualRule === 'NONE_IF_REGISTERED_IN_EVENT_PERIOD' && $eventPeriod < $period => [
                    'status' => 'LATE_REGISTRATION_INTERPRETATION_PENDING', 'basis' => $basis,
                    'from_date' => $event['event_date'], 'components' => [], 'computed' => false,
                    'dependency' => 'REVISAO_JURIDICA_ACRESCIMOS_EM_REGISTRO_TARDIO'],
                default => ['status' => 'NOT_APPLICABLE', 'basis' => $basis, 'computed' => false],
            };
            $ledger[$credit['credit_id']] = [...$credit, 'adjusted' => $next->rounded(2)->value(),
                'registered_this_period' => DecimalValue::fromString($credit['registered_this_period'])
                    ->plus(DecimalValue::fromString((string) $amount))->rounded(2)->value()];
            $entry = ['event_id' => $eventId, 'credit_id' => $credit['credit_id'], 'tax' => $credit['tax'], 'kind' => $kind,
                'cause' => $cause, 'legal_basis' => $basis, 'amount' => DecimalValue::fromString((string) $amount)->rounded(2)->value(),
                'event_date' => $event['event_date'], 'event_period' => $eventPeriod, 'registration_period' => $period,
                'evidence_id' => $event['evidence_id'], 'document_id' => $credit['document_id'], 'legal_additions' => $accrual];
            $registered[] = $entry;
            $trace[] = ['stage' => 'B11_ADJUSTMENT_REGISTERED', 'event_id' => $eventId, 'entry_hash' => CanonicalJson::hash($entry)];
        }
        $totals = ['IBS' => '0.00', 'CBS' => '0.00'];
        $balances = [];
        foreach ($ledger as $id => $credit) {
            $remaining = DecimalValue::fromString($credit['appropriated'])->minus(DecimalValue::fromString($credit['adjusted']))->rounded(2)->value();
            $balances[] = [...$credit, 'available_for_consumption' => $remaining];
            $totals[$credit['tax']] = DecimalValue::fromString($totals[$credit['tax']])
                ->plus(DecimalValue::fromString($credit['registered_this_period']))->rounded(2)->value();
        }
        usort($balances, fn (array $a, array $b): int => strcmp($a['credit_id'], $b['credit_id']));
        $result = ['status' => 'RESOLVED', 'reason' => 'B11_ADJUSTMENTS_REGISTERED', 'version' => self::VERSION,
            'registration_period' => $period, 'registered' => $registered, 'deferred' => $deferred,
            'period_adjustment_totals' => $totals, 'balances' => $balances, 'trace' => $trace, ...$this->limits()];

        return [...$result, 'result_hash' => CanonicalJson::hash($result)];
    }

    private function money(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A\d{1,15}(\.\d{1,2})?\z/', $value) === 1;
    }

    private function date(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) === 1
            && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4));
    }

    /** @return array<string, bool> */
    private function limits(): array
    {
        return ['payment_or_extinction_proven' => false, 'legal_additions_computed' => false,
            'official_homologation' => false, 'production_ready' => false];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function stop(string $status, string $reason, array $context = []): array
    {
        return ['status' => $status, 'reason' => $reason, ...$context, ...$this->limits()];
    }
}
