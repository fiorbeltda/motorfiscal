<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Decision\FiscalDecisionContext;
use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Facts\FactDefinition;
use App\Modules\Fiscal\Core\Facts\FactDefinitionRegistry;
use App\Modules\Fiscal\Core\Facts\FactSourceType;
use App\Modules\Fiscal\Core\Facts\FactValue;
use App\Modules\Fiscal\Core\Facts\FactValueType;
use App\Modules\Fiscal\Core\Rules\ConditionEvaluator;
use App\Modules\Fiscal\Core\Rules\RawRule;
use App\Modules\Fiscal\Core\Rules\RuleCompilationException;
use App\Modules\Fiscal\Core\Rules\RuleCompiler;
use App\Modules\Fiscal\Core\Rules\RuleEvaluator;
use App\Modules\Fiscal\Core\Rules\RuleMatchStatus;
use App\Modules\Fiscal\Core\Shared\LocalDate;

function ruleTestRegistry(): FactDefinitionRegistry
{
    return new FactDefinitionRegistry([
        new FactDefinition('provider.tax_regime', FactValueType::ENUM, allowedValues: ['SIMPLES_NACIONAL', 'LUCRO_REAL']),
        new FactDefinition('operation.amount', FactValueType::DECIMAL),
    ]);
}

function syntheticRule(): array
{
    return json_decode((string) file_get_contents(resource_path('fiscal/rules/synthetic/provider-simples.json')), true, 512, JSON_THROW_ON_ERROR);
}

it('compiles and evaluates a valid typed rule', function (): void {
    $registry = ruleTestRegistry();
    $rule = (new RuleCompiler($registry))->compile(new RawRule(syntheticRule()));
    $facts = new FactBag($registry);
    $facts->put(new FactValue(
        key: 'provider.tax_regime',
        value: 'SIMPLES_NACIONAL',
        sourceType: FactSourceType::USER,
        effectiveFrom: LocalDate::fromString('2026-01-01'),
        effectiveTo: null,
        recordedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
    ));
    $context = new FiscalDecisionContext(
        $facts,
        LocalDate::fromString('2026-06-01'),
        new DateTimeImmutable('2026-06-01T00:00:00Z'),
        'synthetic-v1',
    );

    $result = (new RuleEvaluator(new ConditionEvaluator))->evaluate($rule, $context);

    expect($result->status)->toBe(RuleMatchStatus::MATCHED)
        ->and($result->effects)->toHaveCount(1);
});

it('returns the exact missing facts instead of a false result', function (): void {
    $registry = ruleTestRegistry();
    $rule = (new RuleCompiler($registry))->compile(new RawRule(syntheticRule()));
    $context = new FiscalDecisionContext(
        new FactBag($registry),
        LocalDate::fromString('2026-06-01'),
        new DateTimeImmutable('2026-06-01T00:00:00Z'),
        'synthetic-v1',
    );

    $result = (new RuleEvaluator(new ConditionEvaluator))->evaluate($rule, $context);

    expect($result->status)->toBe(RuleMatchStatus::NEEDS_FACTS)
        ->and($result->missingFacts)->toBe(['provider.tax_regime']);
});

it('rejects operators incompatible with fact types', function (): void {
    $payload = syntheticRule();
    $payload['when'] = ['fact' => 'provider.tax_regime', 'operator' => 'GT', 'value' => 'SIMPLES_NACIONAL'];

    (new RuleCompiler(ruleTestRegistry()))->compile(new RawRule($payload));
})->throws(RuleCompilationException::class);
