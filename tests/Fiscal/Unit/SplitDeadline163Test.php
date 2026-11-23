<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SplitDeadline163;

require_once dirname(__DIR__, 3).'/scripts/agent_a/split163_fixture.php';

it('counts the next banking day from liquidation despite a late report', function (): void {
    $report = (new SplitDeadline163)->assess(split163Input());
    expect($report['status'])->toBe('LOCAL_SPLIT_DEADLINE_ASSESSED')
        ->and($report['deadline_at'])->toBe('2026-10-13T12:00:00.000000-03:00')
        ->and($report['target_at'])->toBe('2026-10-13T09:00:00.000000-03:00')
        ->and($report['counted_business_dates'])->toBe(['2026-10-13'])
        ->and($report['timing_status'])->toBe('PENDING_LATE')->and($report['balance_established'])->toBeFalse();
});

it('never restarts the simplified excess clock at a delayed determination', function (): void {
    $report = (new SplitDeadline163)->assess(split163Input('SIMPLIFIED_EXCESS_TRANSFER'));
    expect($report['deadline_date'])->toBe('2026-11-05')->and($report['deadline_precision'])->toBe('DATE')
        ->and($report['deadline_at'])->toBeNull()->and($report['target_at'])->toBeNull()
        ->and($report['counted_business_dates'])->toBe(['2026-11-03', '2026-11-04', '2026-11-05']);
});

it('uses each arrangement first required cutoff with inclusive microsecond boundaries', function (string $arrangement, string $cut, string $limit): void {
    $input = split163Input('SEGREGATION_REPORT');
    $input['events']['arrangement'] = $arrangement;
    $input['events']['financial_settlement']['at'] = '2026-10-06T'.$cut.'-03:00';
    $service = new SplitDeadline163;
    $report = $service->assess(seal158($input));
    expect($report['deadline_at'])->toBe('2026-10-06T'.$limit.'.000000-03:00')
        ->and($report['clock_started_at'])->toBe('2026-10-06T'.$cut.'.000000-03:00');
    $input['events']['financial_settlement']['at'] = '2026-10-06T'.$cut.'.000001-03:00';
    expect($service->assess(seal158($input))['deadline_at'])->not->toBe($report['deadline_at']);
})->with([
    ['BOLETO', '08:20:00', '11:20:00'], ['PIX_DYNAMIC', '12:00:00', '15:00:00'],
    ['PIX_AUTOMATIC', '12:00:00', '15:00:00'], ['PIX_STATIC', '11:50:00', '14:50:00'],
    ['TED', '12:30:00', '15:30:00'], ['TEF', '12:10:00', '15:10:00'],
]);

it('moves after the second cutoff to the next explicitly classified business date', function (): void {
    $input = split163Input('SEGREGATION_REPORT');
    $input['events']['financial_settlement']['at'] = '2026-10-09T16:10:00.000001-03:00';
    expect((new SplitDeadline163)->assess(seal158($input))['deadline_at'])->toBe('2026-10-13T11:20:00.000000-03:00');
    $input['events']['financial_settlement']['at'] = '2026-10-10T08:00:00-03:00';
    expect((new SplitDeadline163)->assess(seal158($input))['deadline_at'])->toBe('2026-10-13T11:20:00.000000-03:00');
});

it('checks maximum and functional target independently without rounding timestamps', function (string $at, string $status, bool $target): void {
    $input = split163Input();
    $input['events']['segregation_report']['at'] = '2026-10-09T16:00:00Z';
    $input['performance'] = split163Event('PERFORMANCE.PROOF', '2026-10-13T'.$at.'-03:00');
    $report = (new SplitDeadline163)->assess(seal158($input));
    expect($report['timing_status'])->toBe($status)->and($report['target_met'])->toBe($target);
})->with([['09:00:00', 'PERFORMED_WITHIN_DEADLINE', true], ['09:00:00.000001', 'PERFORMED_WITHIN_DEADLINE', false],
    ['12:00:00', 'PERFORMED_WITHIN_DEADLINE', false], ['12:00:00.000001', 'PERFORMED_LATE', false]]);

it('does not invent an intraday deadline for a date-only legal rule', function (string $at, string $status): void {
    $input = split163Input('SIMPLIFIED_EXCESS_TRANSFER');
    $input['events']['excess_determined']['at'] = '2026-11-03T12:00:00Z';
    $input['performance'] = split163Event('TRANSFER.PROOF', $at);
    expect((new SplitDeadline163)->assess(seal158($input))['timing_status'])->toBe($status);
})->with([['2026-11-05T23:59:59.999999-03:00', 'PERFORMED_WITHIN_DEADLINE'], ['2026-11-06T00:00:00-03:00', 'PERFORMED_LATE']]);

it('refuses missing coverage, ambiguous provenance, stale parameters and contradictory events', function (string $case): void {
    $input = split163Input();
    match ($case) {
        'tax' => $input['tax'] = 'IBS',
        'purpose' => $input['synthetic_calendar']['purpose'] = 'TAX_AUTHORITY_EXPEDIENT',
        'authority' => $input['synthetic_calendar']['authority'] = 'RFB',
        'timezone' => $input['synthetic_calendar']['timezone'] = 'America/Sao_Paulo',
        'environment' => $input['synthetic_calendar']['environment'] = 'PRODUCTION',
        'missing date' => $input['synthetic_calendar']['days']['2026-10-12'] = null,
        'extra date' => $input['synthetic_calendar']['days']['2026-12-01'] = false,
        'gap' => $input['events']['financial_settlement']['at'] = '2026-08-31T12:00:00Z',
        'future coverage' => $input['events']['financial_settlement']['at'] = '2026-11-30T12:00:00Z',
        'published later' => $input['synthetic_calendar']['published_at'] = '2026-11-24T00:00:00Z',
        'revoked' => $input['synthetic_calendar']['revoked_at'] = '2026-11-01T00:00:00Z',
        'superseded' => $input['synthetic_calendar']['superseded_at'] = '2026-11-01T00:00:00Z',
        'expired' => $input['synthetic_calendar']['valid_until'] = '2026-11-15',
        'documentary synthetic' => $input['source_kind'] = 'DOCUMENTARY_INPUT',
        'self declared official' => $input['synthetic_calendar']['source_kind'] = 'OFFICIAL_PUBLISHED',
        'missing calendar' => $input['synthetic_calendar'] = null,
        'report before settlement' => $input['events']['segregation_report']['at'] = '2026-10-01T12:00:00Z',
        'as of future' => $input['as_of'] = '2026-11-24T12:00:00Z',
        'performance before report' => $input['performance'] = split163Event('PERFORMANCE.PROOF', '2026-10-09T16:00:00Z'),
        'extra event' => $input['events']['arbitrary_start'] = split163Event('EXTRA.PROOF', '2026-10-09T12:00:00Z'),
        'unqualified rule' => $input['rule'] = 'ACQUIRER_EXCESS_TRANSFER',
        'event evidence reuse' => $input['events']['segregation_report']['evidence_id'] = $input['events']['financial_settlement']['evidence_id'],
    };
    expect((new SplitDeadline163)->assess(seal158($input))['status'])->toBe('ACTION_REQUIRED');
})->with(['tax', 'purpose', 'authority', 'timezone', 'environment', 'missing date', 'extra date', 'gap', 'future coverage',
    'published later', 'revoked', 'superseded', 'expired', 'documentary synthetic', 'self declared official', 'missing calendar',
    'report before settlement', 'as of future', 'performance before report', 'extra event', 'unqualified rule', 'event evidence reuse']);

it('replays deterministically and rejects edited reports and unsealed inputs', function (): void {
    $service = new SplitDeadline163;
    $input = split163Input();
    $report = $service->assess($input);
    expect($service->replay($report))->toBeTrue()->and($service->assess($input))->toBe($report);
    $report['deadline_date'] = '2026-10-14';
    expect($service->replay($report))->toBeFalse();
    $input['tax'] = 'IBS';
    expect($service->assess($input)['reason'])->toBe('A162_INPUT_HASH_CONFLICT');
});

it('refuses duplicate calendar identifiers and does not promote external caller calendars to official', function (): void {
    $input = split163Input();
    expect((new SplitDeadline163([$input['synthetic_calendar']]))->assess($input)['reason'])->toBe('A163_CALENDAR_ID_AMBIGUOUS');
    $calendar = $input['synthetic_calendar'];
    $calendar['source_kind'] = 'OFFICIAL_PUBLISHED';
    $input['source_kind'] = 'DOCUMENTARY_INPUT';
    $input['synthetic_calendar'] = null;
    $service = new SplitDeadline163([$calendar]);
    $report = $service->assess(seal158($input));
    expect($report['status'])->toBe('LOCAL_SPLIT_DEADLINE_ASSESSED')->and($report['official'])->toBeFalse();
    $calendar['tax'] = 'IBS';
    expect((new SplitDeadline163([$calendar]))->assess(seal158($input))['reason'])->toBe('A163_CALENDAR_SCOPE_CONFLICT');
});

it('stops exactly when business-date coverage ends instead of inventing the next year', function (): void {
    $input = split163Input();
    $calendar = $input['synthetic_calendar'];
    $calendar['coverage_end'] = '2026-10-09';
    $calendar['days'] = array_filter($calendar['days'], static fn (string $day): bool => $day <= '2026-10-09', ARRAY_FILTER_USE_KEY);
    $input['synthetic_calendar'] = $calendar;
    expect((new SplitDeadline163)->assess(seal158($input))['reason'])->toBe('A163_CALENDAR_COVERAGE_REQUIRED');
});
