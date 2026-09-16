<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Facts\FactDefinition;
use App\Modules\Fiscal\Core\Facts\FactDefinitionRegistry;
use App\Modules\Fiscal\Core\Facts\FactSourceType;
use App\Modules\Fiscal\Core\Facts\FactValue;
use App\Modules\Fiscal\Core\Facts\FactValueType;
use App\Modules\Fiscal\Core\Shared\LocalDate;

function testFact(string $key, mixed $value): FactValue
{
    return new FactValue(
        key: $key,
        value: $value,
        sourceType: FactSourceType::USER,
        effectiveFrom: LocalDate::fromString('2026-01-01'),
        effectiveTo: null,
        recordedAt: new DateTimeImmutable('2026-01-01T00:00:00-03:00'),
    );
}

it('stores only values compatible with registered fact definitions', function (): void {
    $registry = new FactDefinitionRegistry([
        new FactDefinition('operation.amount', FactValueType::DECIMAL),
    ]);
    $bag = new FactBag($registry);
    $bag->put(testFact('operation.amount', '100.25'));

    expect($bag->value('operation.amount'))->toBe('100.25');
});

it('rejects floats for decimal facts', function (): void {
    $registry = new FactDefinitionRegistry([
        new FactDefinition('operation.amount', FactValueType::DECIMAL),
    ]);
    $bag = new FactBag($registry);

    $bag->put(testFact('operation.amount', 100.25));
})->throws(InvalidArgumentException::class);

it('uses inclusive fiscal validity boundaries', function (): void {
    $fact = new FactValue(
        key: 'service.code',
        value: '010101',
        sourceType: FactSourceType::CATALOG,
        effectiveFrom: LocalDate::fromString('2026-01-01'),
        effectiveTo: LocalDate::fromString('2026-12-31'),
        recordedAt: new DateTimeImmutable('2025-12-01T00:00:00Z'),
    );

    expect($fact->isEffectiveOn(LocalDate::fromString('2026-01-01')))->toBeTrue()
        ->and($fact->isEffectiveOn(LocalDate::fromString('2026-12-31')))->toBeTrue()
        ->and($fact->isEffectiveOn(LocalDate::fromString('2027-01-01')))->toBeFalse();
});
