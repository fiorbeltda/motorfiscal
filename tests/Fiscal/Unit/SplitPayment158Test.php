<?php

declare(strict_types=1);

use App\Modules\Fiscal\ConsumptionReform\SplitPayment158;
use App\Modules\Fiscal\Core\Shared\CanonicalJson;

require_once dirname(__DIR__, 3).'/scripts/agent_a/split158_fixture.php';

it('calculates exact proportionality independently and truncates each tax only at the end', function (): void {
    $service = new SplitPayment158;
    $report = $service->calculateSegregation(split158Calculation());
    expect($report['taxes']['IBS']['segregated'])->toBe('3.33')
        ->and($report['taxes']['CBS']['segregated'])->toBe('6.66')
        ->and($report['calculation_actor'])->toBe('NUCLEA')
        ->and($report['official'])->toBeFalse()->and($report['settlement_proven'])->toBeFalse()
        ->and($report['extinction_proven'])->toBeFalse()->and($service->replay($report))->toBeTrue();
    $report['taxes']['IBS']['segregated'] = '3.34';
    unset($report['report_hash']);
    $report['report_hash'] = CanonicalJson::hash($report);
    expect($service->replay($report))->toBeFalse();
});

it('uses correction and outstanding independently including an explicit zero', function (): void {
    $input = split158Calculation();
    $input['taxes'] = ['IBS' => ['informed' => '10.00', 'corrected' => '12.00', 'outstanding' => '2.00'],
        'CBS' => ['informed' => '20.00', 'corrected' => '0.00', 'outstanding' => '0.00']];
    $report = (new SplitPayment158)->calculateSegregation(seal158($input));
    expect($report['taxes']['IBS']['segregated'])->toBe('2.00')
        ->and($report['taxes']['IBS']['reference'])->toBe('12.00')
        ->and($report['taxes']['CBS']['segregated'])->toBe('0.00');
});

it('caps interest or overpayment at reference and never increases the reference amount', function (): void {
    $input = split158Calculation();
    $input['paid_amount'] = '120.00';
    $report = (new SplitPayment158)->calculateSegregation(seal158($input));
    expect($report['taxes']['IBS']['segregated'])->toBe('10.00')->and($report['taxes']['CBS']['segregated'])->toBe('20.00');
});

it('does not round an intermediate rational ratio up to a cent', function (): void {
    $input = split158Calculation();
    $input['original_amount'] = '3.00';
    $input['paid_amount'] = '1.00';
    $input['taxes']['IBS']['informed'] = '0.03';
    $input['taxes']['CBS']['informed'] = '0.03';
    $report = (new SplitPayment158)->calculateSegregation(seal158($input));
    expect($report['taxes']['IBS']['segregated'])->toBe('0.01');
});

it('uses informed amounts directly for payer initiated arrangements', function (string $arrangement): void {
    $input = split158Calculation();
    $input['arrangement'] = $arrangement;
    $input['originator'] = 'PAYER';
    $report = (new SplitPayment158)->calculateSegregation(seal158($input));
    expect($report['proportionality'])->toBeFalse()->and($report['taxes']['IBS']['segregated'])->toBe('10.00')
        ->and($report['taxes']['CBS']['segregated'])->toBe('20.00');
})->with(['PIX_STATIC', 'TED', 'TEF']);

it('uses receiver proportionality in each supported receiver arrangement', function (string $arrangement): void {
    $input = split158Calculation();
    $input['arrangement'] = $arrangement;
    $report = (new SplitPayment158)->calculateSegregation(seal158($input));
    expect($report['proportionality'])->toBeTrue()->and($report['taxes']['IBS']['segregated'])->toBe('3.33');
})->with(['BOLETO', 'PIX_DYNAMIC', 'PIX_AUTOMATIC']);

it('refuses invalid or out of scope arithmetic inputs', function (string $case): void {
    $input = split158Calculation();
    match ($case) {
        'contract' => $input['contract'] = 'OTHER',
        'manual' => $input['manual_hash'] = str_repeat('0', 64),
        'simplified' => $input['procedure'] = 'SIMPLIFIED',
        'currency' => $input['currency'] = 'USD',
        'official' => $input['source_kind'] = 'OFFICIAL',
        'id' => $input['transaction_id'] = ' ',
        'card' => $input['arrangement'] = 'CARD',
        'originator' => $input['originator'] = 'PAYER',
        'zero original' => $input['original_amount'] = '0.00',
        'negative' => $input['paid_amount'] = '-1.00',
        'float' => $input['paid_amount'] = 1.0,
        'precision' => $input['paid_amount'] = '1.001',
        'missing CBS' => $input['taxes'] = ['IBS' => ['informed' => '1.00']],
        'null correction' => $input['taxes']['IBS']['corrected'] = null,
        'negative outstanding' => $input['taxes']['IBS']['outstanding'] = '-1.00',
        'tax sum exceeds original' => $input['taxes']['CBS']['informed'] = '100.00',
        'payer correction' => $input = array_replace($input, ['arrangement' => 'TED', 'originator' => 'PAYER',
            'taxes' => ['IBS' => ['informed' => '10.00', 'corrected' => '9.00'], 'CBS' => ['informed' => '20.00']]]),
    };
    $report = (new SplitPayment158)->calculateSegregation(seal158($input));
    expect($report['status'])->toBe('ACTION_REQUIRED')->and($report)->not->toHaveKey('taxes');
})->with(['contract', 'manual', 'simplified', 'currency', 'official', 'id', 'card', 'originator', 'zero original',
    'negative', 'float', 'precision', 'missing CBS', 'null correction', 'negative outstanding', 'tax sum exceeds original', 'payer correction']);

it('refuses an input changed without resealing', function (): void {
    $input = split158Calculation();
    $input['paid_amount'] = '50.00';
    expect((new SplitPayment158)->calculateSegregation($input)['reason'])->toBe('A158_INPUT_HASH_CONFLICT');
});
