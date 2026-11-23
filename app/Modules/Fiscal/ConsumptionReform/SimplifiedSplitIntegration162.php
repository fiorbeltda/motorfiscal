<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class SimplifiedSplitIntegration162
{
    public const KEY = 'simplified_split_a162';

    public const FACT = 'rtc.simplified_split_record';

    public const PARAMETERS = 'simplified_split_percentages_a162';

    public const MANDATES = 'simplified_split_mandatory_acts_a162';

    public const DEPLOYMENTS = 'simplified_split_deployments_a162';

    public const POLICY = ['version' => 'A162', 'method' => 'SPLIT_PAYMENT', 'procedure' => 'SIMPLIFIED',
        'calculation_contract' => SimplifiedSplitCalculation162::CONTRACT, 'period_contract' => SimplifiedSplitPeriod162::CONTRACT,
        'law_hash' => SplitLegalEvidence162::LAW_HASH, 'percentage_catalog' => self::PARAMETERS, 'mandate_catalog' => self::MANDATES,
        'deployment_catalog' => self::DEPLOYMENTS, 'rounding' => 'EXPLICIT_QUALIFIED_PARAMETER', 'posting' => false];

    public static function requested(FactBag $facts): bool
    {
        return $facts->value('rtc.split_procedure') === 'SIMPLIFIED';
    }

    /** @param array<string, mixed> $codes */
    public static function valid(array $codes): bool
    {
        if (! is_array($codes[self::KEY] ?? null) || CanonicalJson::hash($codes[self::KEY]) !== CanonicalJson::hash(self::POLICY)) {
            return false;
        }
        foreach ([self::PARAMETERS, self::MANDATES, self::DEPLOYMENTS] as $key) {
            if (! is_array($codes[$key] ?? null) || ! array_is_list($codes[$key])) {
                return false;
            }
            foreach ($codes[$key] as $row) {
                if (! is_array($row)) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @param array<string, mixed> $codes */
    public static function synthetic(array $codes, FactBag $facts): bool
    {
        if (! self::valid($codes) || ! self::requested($facts)) {
            return false;
        }
        $records = $facts->value(self::FACT);
        foreach (is_array($records) ? $records : [] as $record) {
            if (is_array($record) && (($record['calculation']['source_kind'] ?? null) === 'SYNTHETIC_TEST'
                || ($record['period']['source_kind'] ?? null) === 'SYNTHETIC_TEST')) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $codes */
    public static function deployed(array $codes, FactBag $facts, string $on, DateTimeImmutable $known): bool
    {
        if (! self::valid($codes)) {
            return false;
        }
        $g = new SplitLegalEvidence162;
        $matches = 0;
        foreach ($codes[self::DEPLOYMENTS] as $row) {
            if (($row['id'] ?? null) !== $facts->value('rtc.split_deployment_evidence_id') || ($row['procedure'] ?? null) !== 'SIMPLIFIED') {
                continue;
            }
            try {
                $g->lifecycle($row, $on, $known);
                $g->must(($row['source_kind'] ?? null) === 'OFFICIAL_PUBLISHED'
                    || (($row['source_kind'] ?? null) === 'SYNTHETIC_TEST' && self::synthetic($codes, $facts)), 'A162_DEPLOYMENT_PROVENANCE_CONFLICT');
                $g->must(in_array($row['supplier_id'] ?? null, ['*', $facts->value('rtc.supplier_id')], true)
                    && in_array($row['sector'] ?? null, ['*', $facts->value('rtc.simplified_sector')], true), 'A162_DEPLOYMENT_SCOPE_CONFLICT');
                $matches++;
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        return $matches === 1;
    }

    /** @param array<string, mixed> $codes
     * @param  array{IBS: string, CBS: string}  $nominalDebits
     * @return array<string, mixed>
     */
    public function integrate(array $codes, FactBag $facts, string $date, DateTimeImmutable $known, array $nominalDebits): array
    {
        try {
            $g = new SplitLegalEvidence162;
            $g->must(self::valid($codes) && $facts->value('rtc.collection_method') === 'SPLIT_PAYMENT', 'RTC_SIMPLIFIED_SPLIT_POLICY_OR_METHOD_CONFLICT');
            $records = $facts->value(self::FACT);
            $g->must(is_array($records) && array_is_list($records) && count($records) === 1 && is_array($records[0]), 'RTC_SIMPLIFIED_SPLIT_RECORD_REQUIRED');
            $record = $records[0];
            $g->fields($record, ['calculation', 'period']);
            $input = $record['calculation'];
            $g->must(is_array($input), 'RTC_SIMPLIFIED_CALCULATION_REQUIRED');
            foreach (['supplier_id', 'acquirer_id', 'document_id'] as $key) {
                $g->must(($input[$key] ?? null) === $facts->value('rtc.'.$key), 'RTC_SIMPLIFIED_PARTY_OR_DOCUMENT_CONFLICT');
            }
            $regime = $facts->value('rtc.acquirer_regime');
            $g->must(in_array($regime, ['REGULAR', 'SIMPLES_NACIONAL', 'MEI', 'NON_TAXPAYER'], true)
                && ($input['acquirer_regime'] ?? null) === ($regime === 'REGULAR' ? 'REGULAR' : 'NON_REGULAR'), 'RTC_SIMPLIFIED_ACQUIRER_REGIME_CONFLICT');
            $g->must(($input['operation_on'] ?? null) === $date && $g->instant($input['known_at'] ?? null) == $known
                && ($input['payment_transaction_id'] ?? null) === $facts->value('rtc.payment_transaction_id')
                && ($input['sector'] ?? null) === $facts->value('rtc.simplified_sector')
                && ($input['nominal_values_identified'] ?? null) === $facts->value('rtc.split_nominal_values_identified')
                && ($input['option'] ?? null) === $facts->value('rtc.simplified_split_option')
                && $g->money($input['operation_value'] ?? null)->compareTo((string) $facts->value('rtc.gross_amount')) === 0, 'RTC_SIMPLIFIED_OPERATION_FACT_CONFLICT');
            $service = new SimplifiedSplitCalculation162($codes[self::PARAMETERS], $codes[self::MANDATES]);
            $calculation = $service->assess($input);
            $g->must($calculation['status'] === 'LOCAL_SIMPLIFIED_SPLIT_CALCULATED', (string) ($calculation['reason'] ?? 'RTC_SIMPLIFIED_CALCULATION_REFUSED'));
            $period = null;
            if ($record['period'] !== null) {
                $book = $record['period'];
                $g->must(is_array($book) && ($book['supplier_id'] ?? null) === $input['supplier_id']
                    && $g->instant($book['known_at'] ?? null) == $known, 'RTC_SIMPLIFIED_PERIOD_FACT_CONFLICT');
                $settledOn = $g->instant($input['settled_at'])->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d');
                $g->must($g->date($book['period_start'] ?? null) <= $settledOn
                    && $settledOn <= $g->date($book['period_end'] ?? null), 'RTC_SIMPLIFIED_PERIOD_PAYMENT_CONFLICT');
                $g->must($input['source_kind'] !== 'SYNTHETIC_TEST' || ($book['source_kind'] ?? null) === 'SYNTHETIC_TEST', 'RTC_SIMPLIFIED_MIXED_PROVENANCE');
                $period = (new SimplifiedSplitPeriod162)->reconcile($book);
                $g->must($period['status'] === 'LOCAL_SIMPLIFIED_SPLIT_PERIOD_RECONCILED', (string) ($period['reason'] ?? 'RTC_SIMPLIFIED_PERIOD_REFUSED'));
                $matches = [];
                foreach ($book['debts'] as $debt) {
                    if (($debt['document_id'] ?? null) === $input['document_id']) {
                        $matches[] = $debt;
                    }
                }
                $g->must(count($matches) === 1 && $matches[0]['operation_id'] === $input['operation_id']
                    && $matches[0]['operation_on'] === $date && $matches[0]['acquirer_regime'] === $input['acquirer_regime']
                    && CanonicalJson::hash($matches[0]['debits']) === CanonicalJson::hash($nominalDebits), 'RTC_SIMPLIFIED_PERIOD_CURRENT_DEBIT_CONFLICT');
            }

            return ['status' => 'RESOLVED', 'report' => ['calculation' => $calculation, 'period' => $period,
                'extinction_status' => $period === null ? 'UNCONFIRMED_PERIOD_BOOK_REQUIRED' : $period['extinction_status'],
                'appropriable_credit_from_segregation' => false, 'posted' => false]];
        } catch (InvalidArgumentException $exception) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => $exception->getMessage(), 'missing_facts' => [self::FACT]];
        }
    }
}
