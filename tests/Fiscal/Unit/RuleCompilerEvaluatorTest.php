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
use App\Modules\Fiscal\Core\Rules\EvaluationTruth;
use App\Modules\Fiscal\Core\Rules\InMemoryCatalogMembership;
use App\Modules\Fiscal\Core\Rules\RawRule;
use App\Modules\Fiscal\Core\Rules\RuleCompilationException;
use App\Modules\Fiscal\Core\Rules\RuleCompiler;
use App\Modules\Fiscal\Core\Rules\RuleEvaluator;
use App\Modules\Fiscal\Core\Rules\RuleMatchStatus;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use App\Modules\Fiscal\Infrastructure\Files\CompiledRuleFileLoader;

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

it('rejects formulas without explicit argument bindings', function (): void {
    $payload = syntheticRule();
    $payload['kind'] = 'CALCULATION';
    $payload['effects'] = [['type' => 'SELECT_FORMULA', 'formula' => 'PERCENTAGE_OF_BASE']];

    (new RuleCompiler(ruleTestRegistry()))->compile(new RawRule($payload));
})->throws(RuleCompilationException::class);

it('rejects reserved effects that this engine version cannot execute', function (): void {
    $payload = syntheticRule();
    $payload['effects'] = [['type' => 'ADD_CANDIDATE', 'candidate' => 'future-candidate']];

    (new RuleCompiler(ruleTestRegistry()))->compile(new RawRule($payload));
})->throws(RuleCompilationException::class, 'reserved but not supported');

it('evaluates only explicitly registered rule catalogs', function (): void {
    $facts = new FactBag(ruleTestRegistry());
    $facts->put(new FactValue(
        key: 'provider.tax_regime',
        value: 'SIMPLES_NACIONAL',
        sourceType: FactSourceType::USER,
        effectiveFrom: LocalDate::fromString('2026-01-01'),
        effectiveTo: null,
        recordedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
    ));
    $evaluator = new ConditionEvaluator(new InMemoryCatalogMembership(['KNOWN' => ['SIMPLES_NACIONAL']]));

    $known = $evaluator->evaluate(['fact' => 'provider.tax_regime', 'operator' => 'IN_CATALOG', 'catalog' => 'KNOWN'], $facts);
    $unknown = $evaluator->evaluate(['fact' => 'provider.tax_regime', 'operator' => 'IN_CATALOG', 'catalog' => 'MISSING'], $facts);

    expect($known->truth)->toBe(EvaluationTruth::TRUE)
        ->and($unknown->truth)->toBe(EvaluationTruth::ERROR)
        ->and($unknown->errors)->toBe(['Unknown rule catalog: MISSING']);
});

it('rejects precedence references outside the selected bundle', function (): void {
    $payload = syntheticRule();
    $payload['precedence'] = [[
        'type' => 'EXCEPTION_OF',
        'subordinate_rule_id' => 'TEST.MISSING',
        'source_id' => 'SOURCE.SYNTHETIC.TEST',
        'source_version' => 'SYNTHETIC-1',
    ]];

    (new CompiledRuleFileLoader)->fromPayloads([$payload], ruleTestRegistry());
})->throws(RuntimeException::class, 'Rule precedence target not found');
