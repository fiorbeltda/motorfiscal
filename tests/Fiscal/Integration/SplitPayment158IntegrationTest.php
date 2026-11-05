<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

require_once dirname(__DIR__, 3).'/scripts/agent_a/split158_fixture.php';

it('exposes reproducible split arithmetic through the native CLI and refuses unsupported procedures', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'split158-');
    try {
        $input = split158Calculation();
        file_put_contents($path, json_encode($input, JSON_THROW_ON_ERROR));
        expect(Artisan::call('fiscal:rtc:assess-split-payment', ['input' => $path]))->toBe(0);
        $first = Artisan::output();
        $result = json_decode($first, true, 512, JSON_THROW_ON_ERROR);
        expect($result['replay_matches'])->toBeTrue()->and($result['report']['taxes']['IBS']['segregated'])->toBe('3.33')
            ->and($result['report']['taxes']['CBS']['segregated'])->toBe('6.66')
            ->and($result['report']['posted'])->toBeFalse()->and($result['report']['deployment_proven'])->toBeFalse();
        Artisan::call('fiscal:rtc:assess-split-payment', ['input' => $path]);
        expect(Artisan::output())->toBe($first);
        $input['procedure'] = 'SIMPLIFIED';
        file_put_contents($path, json_encode(seal158($input), JSON_THROW_ON_ERROR));
        expect(Artisan::call('fiscal:rtc:assess-split-payment', ['input' => $path]))->toBe(1);
        $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        expect($result['report']['reason'])->toBe('A158_PHASE1_DOES_NOT_IMPLEMENT_SIMPLIFIED')
            ->and($result['report'])->not->toHaveKey('taxes');
    } finally {
        unlink($path);
    }
});

it('returns structured CLI errors for a non object input', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'split158-bad-');
    try {
        file_put_contents($path, '[]');
        expect(Artisan::call('fiscal:rtc:assess-split-payment', ['input' => $path]))->toBe(1);
        $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        expect($result['status'])->toBe('ERROR')->and($result['posted'])->toBeFalse();
    } finally {
        unlink($path);
    }
});

it('assesses a supplied transaction in the CLI without treating pending settlement as a refusal or a payment', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'split158-transaction-');
    try {
        $input = split158Transaction();
        file_put_contents($path, json_encode($input, JSON_THROW_ON_ERROR));
        expect(Artisan::call('fiscal:rtc:assess-split-payment', ['input' => $path, '--mode' => 'transaction']))->toBe(0);
        $first = Artisan::output();
        $result = json_decode($first, true, 512, JSON_THROW_ON_ERROR);
        expect($result['replay_matches'])->toBeTrue()->and($result['report']['segregation']['taxes']['IBS']['segregated'])->toBe('3.99');
        Artisan::call('fiscal:rtc:assess-split-payment', ['input' => $path, '--mode' => 'transaction']);
        expect(Artisan::output())->toBe($first);
        $input['settlement'] = null;
        file_put_contents($path, json_encode(seal158($input), JSON_THROW_ON_ERROR));
        expect(Artisan::call('fiscal:rtc:assess-split-payment', ['input' => $path, '--mode' => 'transaction']))->toBe(0);
        $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        expect($result['report']['financial_state'])->toBe('WAITING_FINANCIAL_SETTLEMENT')
            ->and($result['report']['segregation'])->toBeNull()->and($result['report']['payment_performed'])->toBeFalse();
        $input['document_link']['document_id'] = 'OTHER';
        file_put_contents($path, json_encode(seal158($input), JSON_THROW_ON_ERROR));
        expect(Artisan::call('fiscal:rtc:assess-split-payment', ['input' => $path, '--mode' => 'transaction']))->toBe(1);
        expect(Artisan::call('fiscal:rtc:assess-split-payment', ['input' => $path, '--mode' => 'unknown']))->toBe(1);
    } finally {
        unlink($path);
    }
});

it('reconciles an operation ledger through the native command and refuses unqualified attributions', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'split158-ledger-');
    try {
        $input = split158Ledger();
        file_put_contents($path, json_encode($input, JSON_THROW_ON_ERROR));
        expect(Artisan::call('fiscal:rtc:assess-split-payment', ['input' => $path, '--mode' => 'ledger']))->toBe(0);
        $first = Artisan::output();
        $result = json_decode($first, true, 512, JSON_THROW_ON_ERROR);
        expect($result['replay_matches'])->toBeTrue()->and($result['report']['taxes']['IBS']['split_applied'])->toBe('25.00')
            ->and($result['report']['remittances'][0]['batch_received'])->toBe('100.00')
            ->and($result['report']['taxes']['IBS']['pending_transfer_to_supplier'])->toBe('3.00')
            ->and($result['report']['posted'])->toBeFalse();
        Artisan::call('fiscal:rtc:assess-split-payment', ['input' => $path, '--mode' => 'ledger']);
        expect(Artisan::output())->toBe($first);
        $input['applications'][0]['amount'] = '100.00';
        file_put_contents($path, json_encode(sealLedger158($input), JSON_THROW_ON_ERROR));
        expect(Artisan::call('fiscal:rtc:assess-split-payment', ['input' => $path, '--mode' => 'ledger']))->toBe(1);
        $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        expect($result['report']['reason'])->toBe('A158_LEDGER_APPLICATION_EXCEEDS_DEBIT')
            ->and($result['report'])->not->toHaveKey('taxes');
    } finally {
        unlink($path);
    }
});
