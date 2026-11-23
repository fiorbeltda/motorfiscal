<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use App\Modules\Fiscal\Core\Shared\CanonicalJson;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Local timing assessment; neither authenticates events nor establishes a payable balance. */
final readonly class SplitDeadline163
{
    public const CONTRACT = 'RTC-SPLIT-DEADLINE-A163-V1';

    public const TIMES_HASH = '13685b4d1a2f486cd092efb34c83e741b4300edf9692b09ffb6ce0f27036bdf1';

    public const CUTS = ['BOLETO' => ['08:20:00', '16:10:00'], 'PIX_DYNAMIC' => ['12:00:00', '18:30:00'],
        'PIX_AUTOMATIC' => ['12:00:00', '18:30:00'], 'PIX_STATIC' => ['11:50:00', '18:20:00'],
        'TED' => ['12:30:00', '19:00:00'], 'TEF' => ['12:10:00', '18:40:00']];

    /** @param list<array<string, mixed>> $calendars */
    public function __construct(private array $calendars = []) {}

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function assess(array $input): array
    {
        try {
            return $this->calculate($input);
        } catch (InvalidArgumentException $exception) {
            return ['status' => 'ACTION_REQUIRED', 'reason' => $exception->getMessage(), 'official' => false, 'posted' => false];
        }
    }

    /** @param array<string, mixed> $report */
    public function replay(array $report): bool
    {
        return is_array($report['input'] ?? null)
            && CanonicalJson::hash($this->assess($report['input'])) === CanonicalJson::hash($report);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function calculate(array $input): array
    {
        $g = new SplitLegalEvidence162;
        $g->envelope($input, ['contract', 'currency', 'source_kind', 'source_hash', 'evidence_id', 'input_hash', 'known_at',
            'as_of', 'rule', 'tax', 'reference_id', 'calendar_id', 'synthetic_calendar', 'events', 'performance'], self::CONTRACT);
        $g->id($input['reference_id']);
        $calendarId = $g->id($input['calendar_id']);
        $known = $g->instant($input['known_at']);
        $asOf = $g->instant($input['as_of']);
        $g->must($asOf <= $known, 'A163_AS_OF_AFTER_KNOWLEDGE');
        $g->must(in_array($input['rule'], ['PSP_REMITTANCE', 'SEGREGATION_REPORT', 'SIMPLIFIED_EXCESS_TRANSFER'], true), 'A163_RULE_NOT_QUALIFIED');
        $g->must(in_array($input['tax'], ['IBS', 'CBS'], true), 'A163_TAX_REQUIRED');
        $purpose = $input['rule'] === 'SIMPLIFIED_EXCESS_TRANSFER' ? 'TAX_AUTHORITY_EXPEDIENT' : 'PSP_OPERATIONS';
        $records = $this->calendars;
        if ($input['synthetic_calendar'] !== null) {
            $g->must($input['source_kind'] === 'SYNTHETIC_TEST' && is_array($input['synthetic_calendar'])
                && ($input['synthetic_calendar']['source_kind'] ?? null) === 'SYNTHETIC_TEST', 'A163_SYNTHETIC_CALENDAR_NOT_OFFICIAL');
            $records[] = $input['synthetic_calendar'];
        }
        $ids = [];
        $selected = null;
        foreach ($records as $record) {
            $id = $g->id($record['id'] ?? null);
            $g->must(! in_array($id, $ids, true), 'A163_CALENDAR_ID_AMBIGUOUS');
            $ids[] = $id;
            if ($id === $calendarId) {
                $selected = $record;
            }
        }
        $g->must(is_array($selected), 'A163_QUALIFIED_CALENDAR_REQUIRED');
        $calendar = new SplitCalendar163($selected, $purpose, $input['tax'], $known, $input['source_kind'] === 'SYNTHETIC_TEST');
        $events = $input['events'];
        $g->must(is_array($events), 'A163_EVENTS_REQUIRED');
        $zone = new DateTimeZone('Etc/GMT+3');
        $eventFields = $input['rule'] === 'SIMPLIFIED_EXCESS_TRANSFER' ? ['apuration_concluded', 'excess_determined']
            : ($input['rule'] === 'PSP_REMITTANCE' ? ['financial_settlement', 'segregation_report'] : ['financial_settlement', 'arrangement']);
        $g->fields($events, $eventFields);
        $eventTimes = [];
        $evidenceIds = [];
        foreach ($events as $name => $event) {
            if ($name === 'arrangement') {
                $g->must(is_string($event) && array_key_exists($event, self::CUTS), 'A163_ARRANGEMENT_REQUIRED');

                continue;
            }
            $g->must(is_array($event), 'A163_EVENT_RECORD_REQUIRED');
            $g->fields($event, ['at', 'source_hash', 'evidence_id']);
            $g->proof($event);
            $g->must(! in_array($event['evidence_id'], $evidenceIds, true), 'A163_EVENT_EVIDENCE_REUSED');
            $evidenceIds[] = $event['evidence_id'];
            $time = $g->instant($event['at']);
            $g->must($time <= $asOf, 'A163_FUTURE_EVENT');
            $eventTimes[$name] = $time->setTimezone($zone);
        }
        $counted = [];
        $target = null;
        $deadline = null;
        $clockStart = null;
        if ($input['rule'] === 'SIMPLIFIED_EXCESS_TRANSFER') {
            $trigger = $eventTimes['apuration_concluded'];
            $g->must($eventTimes['excess_determined'] >= $trigger, 'A163_EXCESS_BEFORE_APURATION');
            $counted = $calendar->following($trigger->format('Y-m-d'), 3);
            $dueDate = $counted[2];
            $source = SplitLegalEvidence162::LAW_HASH;
            $interpretation = 'LC214_ART33_PAR4_EXCLUDE_CONCLUSION_DATE_NO_RESTART_ON_DETERMINATION';
        } else {
            $trigger = $eventTimes['financial_settlement'];
            $on = $trigger->format('Y-m-d');
            if ($input['rule'] === 'PSP_REMITTANCE') {
                $g->must($eventTimes['segregation_report'] >= $trigger, 'A163_REPORT_BEFORE_SETTLEMENT');
                $counted = $calendar->following($on, 1);
                $dueDate = $counted[0];
                $target = new DateTimeImmutable($dueDate.'T09:00:00-03:00');
                $deadline = new DateTimeImmutable($dueDate.'T12:00:00-03:00');
                $interpretation = 'TIMES_6_1_SPECIFIC_LIQUIDATION_CLOCK_PREVAILS_OVER_5_3_GENERAL_REPORT_LABEL';
            } else {
                $arrangement = $events['arrangement'];
                $g->must(is_string($arrangement) && array_key_exists($arrangement, self::CUTS), 'A163_ARRANGEMENT_REQUIRED');
                $cuts = self::CUTS[$arrangement];
                $cutoff = null;
                if ($calendar->business($on)) {
                    foreach ($cuts as $cut) {
                        $candidate = new DateTimeImmutable($on.'T'.$cut.'-03:00');
                        if ($trigger <= $candidate) {
                            $cutoff = $candidate;
                            break;
                        }
                    }
                }
                if ($cutoff === null) {
                    $counted = $calendar->following($on, 1);
                    $cutoff = new DateTimeImmutable($counted[0].'T'.$cuts[0].'-03:00');
                }
                $deadline = $cutoff->modify('+3 hours');
                $clockStart = $cutoff;
                $dueDate = $deadline->format('Y-m-d');
                $target = $cutoff;
                $interpretation = 'TIMES_5_2_FIRST_REQUIRED_CUT_INCLUSIVE_OPTIONAL_EARLY_INCLUSION_PRESERVED';
            }
            $source = self::TIMES_HASH;
        }
        $performance = $input['performance'];
        $performed = null;
        if ($performance !== null) {
            $g->must(is_array($performance), 'A163_PERFORMANCE_RECORD_REQUIRED');
            $g->fields($performance, ['at', 'source_hash', 'evidence_id']);
            $g->proof($performance);
            $g->must(! in_array($performance['evidence_id'], $evidenceIds, true), 'A163_PERFORMANCE_EVIDENCE_REUSED');
            $performed = $g->instant($performance['at'])->setTimezone($zone);
            $minimum = $input['rule'] === 'PSP_REMITTANCE' ? $eventTimes['segregation_report']
                : ($input['rule'] === 'SIMPLIFIED_EXCESS_TRANSFER' ? $eventTimes['excess_determined'] : $trigger);
            $g->must($performed >= $minimum && $performed <= $asOf, 'A163_PERFORMANCE_CHRONOLOGY_CONFLICT');
        }
        $late = $deadline === null ? ($performed ?? $asOf)->setTimezone($zone)->format('Y-m-d') > $dueDate
            : ($performed ?? $asOf) > $deadline;
        $report = ['status' => 'LOCAL_SPLIT_DEADLINE_ASSESSED', 'input' => $input,
            'reference_id' => $input['reference_id'], 'rule' => $input['rule'], 'tax' => $input['tax'],
            'calendar_id' => $calendarId, 'calendar_hash' => CanonicalJson::hash($selected), 'rule_source_hash' => $source,
            'interpretation' => $interpretation, 'clock_started_at' => ($clockStart ?? $trigger)->format('Y-m-d\TH:i:s.uP'),
            'counted_business_dates' => $counted, 'deadline_date' => $dueDate, 'deadline_precision' => $deadline === null ? 'DATE' : 'INSTANT',
            'target_at' => $target?->format('Y-m-d\TH:i:s.uP'), 'deadline_at' => $deadline?->format('Y-m-d\TH:i:s.uP'),
            'timing_status' => ($performed === null ? 'PENDING_' : 'PERFORMED_').($late ? 'LATE' : 'WITHIN_DEADLINE'),
            'performance_basis' => 'SUPPLIED_EVENT_TIMESTAMP_NOT_A_FINDING_OF_LEGAL_LIABILITY',
            'ans_exclusions_assessed' => false,
            'target_met' => $target !== null && $performed !== null ? $performed <= $target : null,
            'official' => false, 'posted' => false, 'payment_performed' => false, 'balance_established' => false];
        $report['report_hash'] = CanonicalJson::hash($report);

        return $report;
    }
}
