<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\NFSe\DereAccessKey;
use App\Modules\Fiscal\NFSe\DereRounding;

it('matches the official alphanumeric mother key example and preserves its DV for children', function (): void {
    $keys = new DereAccessKey;
    $key = $keys->group('1C345G7Z', 'BR', '1', '1G01J3A5000191', '1020',
        LocalDate::fromString('2026-05-01'), LocalDate::fromString('2026-05-05'), 1);
    expect($key)->toBe('1C345G7ZBR10000001G01J3A50001911020202605010500012000')
        ->and($keys->isStructurallyValid($key))->toBeTrue()
        ->and($keys->operation($key, 999))->toBe(substr($key, 0, 50).'999');
    $corrupted = substr_replace($key, '3', 49, 1);
    expect($keys->isStructurallyValid($corrupted))->toBeFalse();
});

it('pads CPF and truncates foreign registration to its last20 characters', function (): void {
    $keys = new DereAccessKey;
    $first = LocalDate::fromString('2028-02-29');
    $cpf = $keys->group('12345678', 'BR', '2', '12345678909', '1005', $first, $first, 9999);
    expect(substr($cpf, 11, 20))->toBe('00000000012345678909')->and($keys->isStructurallyValid($cpf))->toBeTrue();
    $foreign = $keys->group('12345678', 'US', '3', 'ABC012345678901234567890', '1005', $first, $first, 1);
    expect(substr($foreign, 11, 20))->toBe('12345678901234567890');
});

it('rejects grouping across months reversed days and invalid series', function (string $first, string $last, int $series): void {
    expect(fn () => (new DereAccessKey)->group('12345678', 'BR', '1', '12345678000195', '1005',
        LocalDate::fromString($first), LocalDate::fromString($last), $series))->toThrow(InvalidArgumentException::class);
})->with([['2026-01-31', '2026-02-01', 1], ['2026-01-20', '2026-01-19', 1],
    ['2026-01-01', '2026-01-01', 0], ['2026-01-01', '2026-01-01', 10000]]);

it('uses the official annexII rounding examples including exact half to even', function (string $value, string $expected): void {
    expect((new DereRounding)->finalAmount($value))->toBe($expected);
})->with([['18.234', '18.23'], ['18.237', '18.24'], ['18.2351', '18.24'],
    ['18.245', '18.24'], ['18.235', '18.24'], ['18.245000', '18.24'], ['-18.245', '-18.24']]);

it('rounds intermediate at8 and final at2 without binary floating point', function (): void {
    $rounding = new DereRounding;
    expect($rounding->intermediate('1.123456785'))->toBe('1.12345678')
        ->and($rounding->intermediate('1.123456795'))->toBe('1.12345680')
        ->and($rounding->taxAmount('5.00', '0.10'))->toBe('0.00')
        ->and($rounding->taxAmount('15.00', '0.10'))->toBe('0.02')
        ->and($rounding->taxAmount('800.00', '0.90'))->toBe('7.20');
});
