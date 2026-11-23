<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\ConsumptionReform;

use DateTimeImmutable;
use DateTimeZone;

/** An explicit daily calendar, qualified for one authority, tax and purpose. */
final readonly class SplitCalendar163
{
    /** @param array<string, mixed> $record */
    public function __construct(private array $record, private string $purpose, private string $tax,
        private DateTimeImmutable $known, private bool $synthetic)
    {
        $g = new SplitLegalEvidence162;
        $g->fields($record, ['id', 'version', 'environment', 'source_kind', 'status', 'published_at', 'valid_from', 'valid_until',
            'revoked_at', 'superseded_at', 'authority', 'tax', 'purpose', 'timezone', 'coverage_start', 'coverage_end', 'days', 'source_hash', 'evidence_id']);
        $g->id($record['id']);
        $g->id($record['version']);
        $g->must($record['environment'] === 'SANDBOX' && $record['timezone'] === 'Etc/GMT+3', 'A163_CALENDAR_ENVIRONMENT_OR_TIMEZONE_CONFLICT');
        $g->must($record['source_kind'] === 'OFFICIAL_PUBLISHED'
            || ($record['source_kind'] === 'SYNTHETIC_TEST' && $synthetic), 'A163_CALENDAR_PROVENANCE_CONFLICT');
        $g->must(in_array($tax, ['IBS', 'CBS'], true) && $record['tax'] === $tax && $record['purpose'] === $purpose
            && $record['authority'] === ($purpose === 'PSP_OPERATIONS' ? 'PAYMENT_SYSTEM' : ($tax === 'CBS' ? 'RFB' : 'CGIBS')), 'A163_CALENDAR_SCOPE_CONFLICT');
        $start = $g->date($record['coverage_start']);
        $end = $g->date($record['coverage_end']);
        $g->must($start <= $end && is_array($record['days']) && ! array_is_list($record['days']), 'A163_CALENDAR_DAYS_REQUIRED');
        $day = new DateTimeImmutable($start, new DateTimeZone('Etc/GMT+3'));
        $expected = [];
        // Bound untrusted coverage before walking it; no inferred weekdays or holidays.
        $g->must($day->diff(new DateTimeImmutable($end))->days <= 3660, 'A163_CALENDAR_COVERAGE_TOO_LARGE');
        while ($day->format('Y-m-d') <= $end) {
            $on = $day->format('Y-m-d');
            $expected[] = $on;
            $g->must(array_key_exists($on, $record['days']) && is_bool($record['days'][$on]), 'A163_CALENDAR_DAILY_CLASSIFICATION_REQUIRED');
            $day = $day->modify('+1 day');
        }
        $g->must(count($record['days']) === count($expected) && array_diff(array_keys($record['days']), $expected) === [], 'A163_CALENDAR_EXTRANEOUS_DATE');
        $g->lifecycle($record, $start, $known);
        $g->lifecycle($record, $end, $known);
    }

    public function business(string $on): bool
    {
        $g = new SplitLegalEvidence162;
        $g->date($on);
        $g->must(array_key_exists($on, $this->record['days']), 'A163_CALENDAR_COVERAGE_REQUIRED');
        $g->lifecycle($this->record, $on, $this->known);

        return $this->record['days'][$on];
    }

    /** Excludes the event date and includes each explicitly classified business day.
     * @return list<string>
     */
    public function following(string $on, int $count): array
    {
        (new SplitLegalEvidence162)->must($count >= 1 && $count <= 3, 'A163_COUNT_RULE_CONFLICT');
        $this->business($on);
        $date = new DateTimeImmutable($on, new DateTimeZone('Etc/GMT+3'));
        $days = [];
        while (count($days) < $count) {
            $date = $date->modify('+1 day');
            if ($this->business($date->format('Y-m-d'))) {
                $days[] = $date->format('Y-m-d');
            }
        }

        return $days;
    }
}
