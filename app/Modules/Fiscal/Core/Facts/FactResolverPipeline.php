<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Facts;

use App\Modules\Fiscal\Core\Decision\FiscalDecisionContext;
use Throwable;

final readonly class FactResolverPipeline
{
    /** @param list<FactResolver> $resolvers */
    public function __construct(private array $resolvers) {}

    public function resolveAvailable(FiscalDecisionContext $context): FactResolutionResult
    {
        $resolved = [];
        $warnings = [];
        $errors = [];
        $definitions = $context->facts->definitions();
        $knownKeys = array_fill_keys(array_map(static fn (FactDefinition $definition): string => $definition->key, $definitions), true);
        $pending = [];
        $resolversByFact = [];

        foreach ($definitions as $definition) {
            if ($context->facts->has($definition->key)) {
                continue;
            }

            $supported = array_values(array_filter(
                $this->resolvers,
                static fn (FactResolver $resolver): bool => $resolver->supports($definition->key),
            ));
            if ($supported === []) {
                continue;
            }
            $pending[$definition->key] = $definition;
            $resolversByFact[$definition->key] = $supported;
        }

        $cycleFacts = $this->cycleFacts($resolversByFact, $context->facts);
        if ($cycleFacts !== []) {
            $errors[] = 'FACT_RESOLVER_CYCLE:'.implode(',', $cycleFacts);
            foreach ($cycleFacts as $cycleFact) {
                unset($pending[$cycleFact]);
            }
        }

        $workingContext = $context;
        do {
            $progress = false;

            foreach ($pending as $factKey => $definition) {
                $candidates = [];
                foreach ($resolversByFact[$factKey] as $resolver) {
                    $dependencies = $resolver->dependencies($factKey);
                    $unknown = array_values(array_filter(
                        $dependencies,
                        static fn (string $dependency): bool => ! isset($knownKeys[$dependency]),
                    ));
                    if ($unknown !== []) {
                        foreach ($unknown as $dependency) {
                            $errors[] = "FACT_RESOLVER_UNKNOWN_DEPENDENCY:{$factKey}:{$dependency}";
                        }

                        continue;
                    }
                    if ($workingContext->facts->missing($dependencies) !== []) {
                        continue;
                    }

                    try {
                        $result = $resolver->resolve($factKey, $workingContext);
                    } catch (Throwable) {
                        $errors[] = 'FACT_RESOLVER_EXCEPTION:'.$factKey;

                        continue;
                    }
                    $warnings = [...$warnings, ...$result->warnings];
                    $errors = [...$errors, ...$result->errors];
                    foreach ($result->facts as $fact) {
                        if ($fact->key !== $factKey || ! $definition->accepts($fact->value)) {
                            $errors[] = 'FACT_RESOLVER_INVALID_OUTPUT:'.$factKey;

                            continue;
                        }
                        $candidates[] = $fact;
                    }
                }

                if (count($candidates) > 1) {
                    $errors[] = 'FACT_RESOLVER_CONFLICT:'.$factKey;
                    unset($pending[$factKey]);

                    continue;
                }
                if ($candidates !== []) {
                    $fact = $candidates[0];
                    $resolved[] = $fact;
                    $workingContext = $this->withFact($workingContext, $fact);
                    unset($pending[$factKey]);
                    $progress = true;
                }
            }
        } while ($progress && $pending !== []);

        return new FactResolutionResult(
            $resolved,
            array_values(array_unique($warnings)),
            array_values(array_unique($errors)),
        );
    }

    private function withFact(FiscalDecisionContext $context, FactValue $fact): FiscalDecisionContext
    {
        return new FiscalDecisionContext(
            $context->facts->withAdditional([$fact]),
            $context->competenceDate,
            $context->knowledgeAt,
            $context->ruleBundleId,
            $context->catalogVersions,
        );
    }

    /**
     * @param  array<string, list<FactResolver>>  $resolversByFact
     * @return list<string>
     */
    private function cycleFacts(array $resolversByFact, FactBag $initialFacts): array
    {
        $graph = [];
        foreach ($resolversByFact as $factKey => $resolvers) {
            $graph[$factKey] = [];
            foreach ($resolvers as $resolver) {
                foreach ($resolver->dependencies($factKey) as $dependency) {
                    if (! $initialFacts->has($dependency) && isset($resolversByFact[$dependency])) {
                        $graph[$factKey][$dependency] = true;
                    }
                }
            }
        }

        $visiting = [];
        $visited = [];
        $cycleFacts = [];
        $visit = function (string $factKey, array $path) use (&$visit, &$visiting, &$visited, &$cycleFacts, $graph): void {
            if (isset($visited[$factKey])) {
                return;
            }
            if (isset($visiting[$factKey])) {
                $cycleStart = array_search($factKey, $path, true);
                if (is_int($cycleStart)) {
                    foreach (array_slice($path, $cycleStart) as $cycleFact) {
                        $cycleFacts[$cycleFact] = true;
                    }
                }

                return;
            }

            $visiting[$factKey] = true;
            $path[] = $factKey;
            foreach (array_keys($graph[$factKey] ?? []) as $dependency) {
                $visit($dependency, $path);
            }
            unset($visiting[$factKey]);
            $visited[$factKey] = true;
        };

        foreach (array_keys($graph) as $factKey) {
            $visit($factKey, []);
        }

        $facts = array_keys($cycleFacts);
        sort($facts, SORT_STRING);

        return $facts;
    }
}
