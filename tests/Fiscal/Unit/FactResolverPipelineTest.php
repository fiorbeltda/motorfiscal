<?php

declare(strict_types=1);

use App\Modules\Fiscal\Core\Decision\FiscalDecisionContext;
use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Facts\FactDefinition;
use App\Modules\Fiscal\Core\Facts\FactDefinitionRegistry;
use App\Modules\Fiscal\Core\Facts\FactResolutionResult;
use App\Modules\Fiscal\Core\Facts\FactResolver;
use App\Modules\Fiscal\Core\Facts\FactResolverPipeline;
use App\Modules\Fiscal\Core\Facts\FactSourceType;
use App\Modules\Fiscal\Core\Facts\FactValue;
use App\Modules\Fiscal\Core\Facts\FactValueType;
use App\Modules\Fiscal\Core\Shared\LocalDate;

function resolverPipelineContext(array $initial = []): FiscalDecisionContext
{
    $definitions = new FactDefinitionRegistry([
        new FactDefinition('fact.a', FactValueType::STRING),
        new FactDefinition('fact.b', FactValueType::STRING),
        new FactDefinition('fact.c', FactValueType::STRING),
    ]);
    $facts = new FactBag($definitions);
    foreach ($initial as $key => $value) {
        $facts->put(resolverPipelineFact($key, $value));
    }

    return new FiscalDecisionContext(
        $facts,
        LocalDate::fromString('2026-09-16'),
        new DateTimeImmutable('2026-09-16T12:00:00Z'),
        'test-bundle',
    );
}

function resolverPipelineFact(string $key, string $value): FactValue
{
    return new FactValue(
        $key,
        $value,
        FactSourceType::DERIVED,
        LocalDate::fromString('2026-09-16'),
        null,
        new DateTimeImmutable('2026-09-16T12:00:00Z'),
    );
}

function resolverFor(string $target, array $dependencies, Closure $value): FactResolver
{
    return new readonly class($target, $dependencies, $value) implements FactResolver
    {
        public function __construct(private string $target, private array $required, private Closure $value) {}

        public function supports(string $factKey): bool
        {
            return $factKey === $this->target;
        }

        public function dependencies(string $factKey): array
        {
            return $this->supports($factKey) ? $this->required : [];
        }

        public function resolve(string $factKey, FiscalDecisionContext $context): FactResolutionResult
        {
            return new FactResolutionResult([
                resolverPipelineFact($factKey, ($this->value)($context)),
            ]);
        }
    };
}

it('resolves an explicit dependency chain incrementally regardless of definition order', function (): void {
    $result = (new FactResolverPipeline([
        resolverFor('fact.c', ['fact.b'], static fn (FiscalDecisionContext $context): string => $context->facts->value('fact.b').'-c'),
        resolverFor('fact.b', ['fact.a'], static fn (FiscalDecisionContext $context): string => $context->facts->value('fact.a').'-b'),
    ]))->resolveAvailable(resolverPipelineContext(['fact.a' => 'a']));

    expect(array_column(array_map(static fn (FactValue $fact): array => $fact->toArray(), $result->facts), 'key'))
        ->toBe(['fact.b', 'fact.c'])
        ->and($result->facts[1]->value)->toBe('a-b-c')
        ->and($result->errors)->toBe([]);
});

it('reports resolver conflicts without selecting an arbitrary value', function (): void {
    $result = (new FactResolverPipeline([
        resolverFor('fact.b', ['fact.a'], static fn (): string => 'first'),
        resolverFor('fact.b', ['fact.a'], static fn (): string => 'second'),
    ]))->resolveAvailable(resolverPipelineContext(['fact.a' => 'a']));

    expect($result->facts)->toBe([])
        ->and($result->errors)->toBe(['FACT_RESOLVER_CONFLICT:fact.b']);
});

it('detects resolver dependency cycles deterministically', function (): void {
    $result = (new FactResolverPipeline([
        resolverFor('fact.a', ['fact.b'], static fn (): string => 'a'),
        resolverFor('fact.b', ['fact.c'], static fn (): string => 'b'),
        resolverFor('fact.c', ['fact.a'], static fn (): string => 'c'),
    ]))->resolveAvailable(resolverPipelineContext());

    expect($result->facts)->toBe([])
        ->and($result->errors)->toBe(['FACT_RESOLVER_CYCLE:fact.a,fact.b,fact.c']);
});
