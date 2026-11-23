<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class SplitDeadlineIntegration163
{
    public const KEY = 'split_deadlines_a163';

    public const FACT = 'rtc.split_deadline_requests';

    public const CALENDARS = 'split_calendars_a163';

    public const POLICY = ['version' => 'A163', 'contract' => SplitDeadline163::CONTRACT,
        'calendar_catalog' => self::CALENDARS, 'law_hash' => SplitLegalEvidence162::LAW_HASH,
        'times_hash' => SplitDeadline163::TIMES_HASH, 'rules' => ['PSP_REMITTANCE', 'SEGREGATION_REPORT', 'SIMPLIFIED_EXCESS_TRANSFER'],
        'reporting_clock' => 'FIRST_REQUIRED_CUT_INCLUSIVE', 'remittance_clock' => 'FINANCIAL_LIQUIDATION_NOT_DELAYED_REPORT',
        'calendar_timezone' => 'Etc/GMT+3', 'coverage' => 'ONLY_EXPLICIT_REQUESTS_NOT_COMPLETE_LEDGER', 'posting' => false];

    /** @param array<string, mixed> $codes */
    public static function valid(array $codes): bool
    {
        if (! is_array($codes[self::KEY] ?? null) || CanonicalJson::hash($codes[self::KEY]) !== CanonicalJson::hash(self::POLICY)
            || ! is_array($codes[self::CALENDARS] ?? null) || ! array_is_list($codes[self::CALENDARS])) {
            return false;
        }
        foreach ($codes[self::CALENDARS] as $calendar) {
            if (! is_array($calendar)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $codes */
    public static function synthetic(array $codes, FactBag $facts): bool
    {
        if (! self::valid($codes)) {
            return false;
        }
        $requests = $facts->value(self::FACT);
        foreach (is_array($requests) ? $requests : [] as $input) {
            if (is_array($input) && ($input['source_kind'] ?? null) === 'SYNTHETIC_TEST') {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $codes
     * @param  array<string, mixed>|null  $standard  Validated ledger report, never raw facts.
     * @param  array<string, mixed>|null  $simplified  Validated simplified report, never raw facts.
     * @return array<string, mixed>
     */
    public function integrate(array $codes, FactBag $facts, DateTimeImmutable $known, ?array $standard, ?array $simplified): array
    {
        try {
            $g = new SplitLegalEvidence162;
            $g->must(self::valid($codes) && $facts->value('rtc.collection_method') === 'SPLIT_PAYMENT', 'RTC_SPLIT_DEADLINE_POLICY_OR_METHOD_CONFLICT');
            $requests = $facts->value(self::FACT);
            $g->must(is_array($requests) && array_is_list($requests) && count($requests) >= 1 && count($requests) <= 500, 'RTC_SPLIT_DEADLINE_REQUESTS_REQUIRED');
            $service = new SplitDeadline163($codes[self::CALENDARS]);
            $reports = [];
            $keys = [];
            foreach ($requests as $input) {
                $g->must(is_array($input), 'RTC_SPLIT_DEADLINE_REQUEST_REQUIRED');
                $report = $service->assess($input);
                $g->must($report['status'] === 'LOCAL_SPLIT_DEADLINE_ASSESSED', (string) ($report['reason'] ?? 'RTC_SPLIT_DEADLINE_REFUSED'));
                $g->must($g->instant($input['known_at']) == $known, 'RTC_SPLIT_DEADLINE_KNOWLEDGE_CONFLICT');
                $key = CanonicalJson::hash([$input['reference_id'], $input['rule'], $input['tax']]);
                $g->must(! in_array($key, $keys, true), 'RTC_SPLIT_DEADLINE_DUPLICATE_REQUEST');
                $keys[] = $key;
                if ($input['rule'] === 'SIMPLIFIED_EXCESS_TRANSFER') {
                    $book = $simplified['period']['input'] ?? null;
                    $g->must(is_array($book) && ($book['phase'] ?? null) === 'CLOSED', 'RTC_SPLIT_DEADLINE_CLOSED_PERIOD_REQUIRED');
                    $this->provenance($input, $book);
                    $this->sameEvent($input['events']['apuration_concluded'], ['at' => $book['period_closed_at'],
                        'evidence_id' => $book['closure_evidence_id'], 'source_hash' => $book['closure_source_hash']]);
                    $matches = [];
                    $transfers = [];
                    foreach ($book['events'] as $event) {
                        if ($event['kind'] === 'EXCESS_DETERMINATION' && $event['id'] === $input['reference_id'] && $event['tax'] === $input['tax']) {
                            $matches[] = $event;
                        }
                        if ($event['kind'] === 'TRANSFER' && $event['determination_id'] === $input['reference_id'] && $event['tax'] === $input['tax']) {
                            $transfers[] = $this->event($event, 'at');
                        }
                    }
                    $g->must(count($matches) === 1, 'RTC_SPLIT_DEADLINE_EXCESS_REFERENCE_CONFLICT');
                    $this->sameEvent($input['events']['excess_determined'], $this->event($matches[0], 'at'));
                    $this->performance($input['performance'], $transfers);
                } else {
                    $book = $standard['input'] ?? null;
                    $g->must(is_array($book), 'RTC_SPLIT_DEADLINE_VALIDATED_LEDGER_REQUIRED');
                    $this->provenance($input, $book);
                    $matches = [];
                    foreach ($book['transactions'] as $transaction) {
                        $payments = ($transaction['contract'] ?? null) === SplitBoletoPayments161::CONTRACT ? $transaction['payments'] : [['transaction' => $transaction, 'payment_id' => null]];
                        foreach ($payments as $payment) {
                            $tx = $payment['transaction'];
                            if (($tx['settlement']['receipt_id'] ?? null) === $input['reference_id']) {
                                $matches[] = ['transaction' => $tx, 'payment_id' => $payment['payment_id']];
                            }
                        }
                    }
                    $g->must(count($matches) === 1, 'RTC_SPLIT_DEADLINE_SETTLEMENT_REFERENCE_CONFLICT');
                    $tx = $matches[0]['transaction'];
                    $this->sameEvent($input['events']['financial_settlement'], $this->event($tx['settlement'], 'settled_at'));
                    $reportMatches = [];
                    foreach ($book['reports'] as $segregation) {
                        $linked = $matches[0]['payment_id'] === null
                            && in_array($tx['transaction_id'], $segregation['transaction_ids'] ?? [], true);
                        foreach ($segregation['payment_references'] ?? [] as $reference) {
                            if ($reference['transaction_id'] === $tx['transaction_id'] && $reference['payment_id'] === $matches[0]['payment_id']) {
                                $linked = true;
                            }
                        }
                        if ($linked) {
                            $on = $g->instant($tx['settlement']['settled_at'])->setTimezone(new DateTimeZone('Etc/GMT+3'))->format('Y-m-d');
                            $g->must($segregation['reference_date'] === $on, 'RTC_SPLIT_DEADLINE_REPORT_REFERENCE_DATE_CONFLICT');
                            $reportMatches[] = $segregation;
                        }
                    }
                    $g->must(count($reportMatches) === 1, 'RTC_SPLIT_DEADLINE_UNIQUE_REPORT_REQUIRED');
                    $segregation = $reportMatches[0];
                    if ($input['rule'] === 'SEGREGATION_REPORT') {
                        $g->must($input['events']['arrangement'] === $segregation['arrangement'], 'RTC_SPLIT_DEADLINE_ARRANGEMENT_CONFLICT');
                        $this->performance($input['performance'], [$this->event($segregation, 'sent_at')]);
                    } else {
                        $this->sameEvent($input['events']['segregation_report'], $this->event($segregation, 'sent_at'));
                        $remittances = [];
                        foreach ($book['remittances'] as $remit) {
                            if ($remit['tax'] === $input['tax'] && in_array($segregation['id'], $remit['report_ids'], true)) {
                                $remittances[] = $this->event($remit, 'received_at');
                            }
                        }
                        $this->performance($input['performance'], $remittances);
                    }
                }
                $reports[] = $report;
            }

            return ['status' => 'RESOLVED', 'report' => ['assessments' => $reports,
                'coverage' => 'ONLY_EXPLICIT_REQUESTS_NOT_COMPLETE_LEDGER', 'financial_completion_proven' => false, 'posted' => false]];
        } catch (InvalidArgumentException $exception) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => $exception->getMessage(), 'missing_facts' => [self::FACT]];
        }
    }

    /** @param array<string, mixed> $input
     * @param  array<string, mixed>  $book
     */
    private function provenance(array $input, array $book): void
    {
        (new SplitLegalEvidence162)->must(($book['source_kind'] ?? null) !== 'SYNTHETIC_TEST' || $input['source_kind'] === 'SYNTHETIC_TEST', 'RTC_SPLIT_DEADLINE_MIXED_PROVENANCE');
    }

    /** @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function event(array $record, string $field): array
    {
        return ['at' => $record[$field], 'evidence_id' => $record['evidence_id'], 'source_hash' => $record['source_hash']];
    }

    /** @param array<string, mixed> $actual
     * @param  array<string, mixed>  $expected
     */
    private function sameEvent(array $actual, array $expected): void
    {
        $g = new SplitLegalEvidence162;
        $g->must($g->instant($actual['at']) == $g->instant($expected['at'])
            && $actual['evidence_id'] === $expected['evidence_id'] && $actual['source_hash'] === $expected['source_hash'], 'RTC_SPLIT_DEADLINE_EVENT_BINDING_CONFLICT');
    }

    /** @param array<string, mixed>|null $actual
     * @param  list<array<string, mixed>>  $expected
     */
    private function performance(?array $actual, array $expected): void
    {
        $g = new SplitLegalEvidence162;
        if ($actual === null) {
            $g->must($expected === [], 'RTC_SPLIT_DEADLINE_KNOWN_PERFORMANCE_OMITTED');

            return;
        }
        $matches = 0;
        foreach ($expected as $event) {
            if ($actual['evidence_id'] === $event['evidence_id']) {
                $this->sameEvent($actual, $event);
                $matches++;
            }
        }
        $g->must($matches === 1, 'RTC_SPLIT_DEADLINE_PERFORMANCE_BINDING_CONFLICT');
    }
}
