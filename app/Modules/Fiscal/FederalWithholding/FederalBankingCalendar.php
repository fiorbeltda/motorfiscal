<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\FederalWithholding;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class FederalBankingCalendar
{
    /** @var array<string, true> */
    private array $holidays;

    /** @param array<string, mixed> $catalog */
    public function __construct(private array $catalog)
    {
        if (($catalog['calendar_id'] ?? null) !== 'FEDERAL.BANKING.NATIONAL.2026'
            || ($catalog['status'] ?? null) !== 'SANDBOX_CANDIDATE'
            || ! is_array($catalog['holidays'] ?? null)
            || ! array_is_list($catalog['holidays'])) {
            throw new InvalidArgumentException('Invalid federal banking calendar.');
        }
        $holidays = [];
        foreach ($catalog['holidays'] as $date) {
            if (! is_string($date) || ! $this->validDate($date) || isset($holidays[$date])) {
                throw new InvalidArgumentException('Invalid or repeated banking holiday.');
            }
            $holidays[$date] = true;
        }
        $this->holidays = $holidays;
    }

    /** @return array{due_date: ?string, status: string, calendar_id: string, calendar_version: string, source_id: string} */
    public function resolve(string $period, string $rule): array
    {
        $base = [
            'due_date' => null,
            'status' => 'BANKING_CALENDAR_REQUIRED',
            'calendar_id' => (string) $this->catalog['calendar_id'],
            'calendar_version' => (string) $this->catalog['version'],
            'source_id' => (string) $this->catalog['source_id'],
        ];
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) !== 1
            || ! in_array($rule, ['DAY_10_NEXT_MONTH_PREVIOUS_BANKING_DAY', 'DAY_15_NEXT_MONTH_PREVIOUS_BANKING_DAY',
                'DAY_15_NEXT_MONTH_NEXT_BANKING_DAY', 'DAY_20_NEXT_MONTH_PREVIOUS_BANKING_DAY',
                'LAST_BUSINESS_DAY_NEXT_MONTH'], true)) {
            return $base;
        }
        $nextMonth = new DateTimeImmutable($period.'-01');
        $nextMonth = $nextMonth->modify('first day of next month');
        $candidate = $rule === 'LAST_BUSINESS_DAY_NEXT_MONTH'
            ? $nextMonth->modify('last day of this month')
            : $nextMonth->setDate((int) $nextMonth->format('Y'), (int) $nextMonth->format('m'),
                match ($rule) {
                    'DAY_20_NEXT_MONTH_PREVIOUS_BANKING_DAY' => 20,
                    'DAY_10_NEXT_MONTH_PREVIOUS_BANKING_DAY' => 10,
                    default => 15,
                });
        if ($candidate->format('Y-m-d') < $this->catalog['coverage_from']
            || $candidate->format('Y-m-d') > $this->catalog['coverage_to']) {
            return $base;
        }
        while ($this->isClosed($candidate)) {
            $candidate = $candidate->modify($rule === 'DAY_15_NEXT_MONTH_NEXT_BANKING_DAY' ? '+1 day' : '-1 day');
        }

        return [...$base, 'due_date' => $candidate->format('Y-m-d'), 'status' => 'NATIONAL_BANKING_DAY_RESOLVED'];
    }

    private function isClosed(DateTimeImmutable $date): bool
    {
        return (int) $date->format('N') >= 6 || isset($this->holidays[$date->format('Y-m-d')]);
    }

    private function validDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
