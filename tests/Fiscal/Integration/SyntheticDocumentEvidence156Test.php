<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3).'/scripts/agent_a/handoff156_fixture.php';

it('blocks final decisions when a new qualified book record is explicitly synthetic', function (): void {
    $input = handoff155Input();
    unset($input['facts']['rtc.acquired_regime']);
    $input['facts']['rtc.credit_book_entries'] = [book156($input['facts'])];
    $result = evaluate156($input, knowledge156('bundles/consumption-phase4b-2026.10.02-1.json'));
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['official'])->toBeFalse()
        ->and($result['decision'])->toBe([])->and($result['reason_codes'])->toContain('RTC_SYNTHETIC_TEST_ONLY');
    $trace = array_column($result['trace'], null, 'stage');
    expect($trace['SYNTHETIC_ARITHMETIC156']['candidate_status'])->toBe('VALID')
        ->and($trace['SYNTHETIC_ARITHMETIC156']['candidate_decision']['ibs']['credit']['amount'])->toBe('2.00');
});

it('retains explicit nonofficial provenance for definitive synthetic import documents and payments', function (): void {
    $result = evaluate156(import156(), knowledge156());
    expect($result['official'])->toBeFalse()->and($result['source_kind'])->toBe('SYNTHETIC_TEST')
        ->and($result['decision'])->toBe([])->and($result['reason_codes'])->toContain('RTC_SYNTHETIC_TEST_ONLY');
    $trace = array_column($result['trace'], null, 'stage');
    expect($trace['IMPORT_CREDIT']['document_evidence156']['import']['payments'][0]['paid_on'])->toBe('2026-09-26');
});

it('also refuses publication through the integrated runner with a synthetic documentary record', function (): void {
    $input = handoff155Input();
    unset($input['facts']['rtc.acquired_regime']);
    $input['facts']['rtc.credit_book_entries'] = [book156($input['facts'])];
    $result = evaluate156($input, knowledge156('bundles/consumption-phase4b-2026.10.02-1.json'), true);
    expect($result['status'])->toBe('ACTION_REQUIRED')->and($result['decision'])->toBe([])
        ->and($result['reason_codes'])->toContain('RTC_SYNTHETIC_TEST_ONLY');
});
