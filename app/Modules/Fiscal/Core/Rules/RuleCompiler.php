<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Rules;

use App\Modules\Fiscal\Core\Facts\FactDefinitionRegistry;
use App\Modules\Fiscal\Core\Facts\FactValueType;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use ValueError;

final readonly class RuleCompiler
{
    public function __construct(private FactDefinitionRegistry $facts) {}

    public function compile(RawRule $raw): CompiledRule
    {
        $payload = $raw->payload;
        $errors = $this->emptyStringList();

        foreach (['rule_id', 'version', 'kind', 'effective_from', 'when', 'effects', 'source_id', 'reason_codes'] as $field) {
            if (! array_key_exists($field, $payload)) {
                $errors[] = "Missing required field: {$field}.";
            }
        }

        if ($errors !== []) {
            throw new RuleCompilationException($errors);
        }

        try {
            $kind = RuleKind::from((string) $payload['kind']);
        } catch (ValueError) {
            $errors[] = 'Unknown rule kind.';
            $kind = RuleKind::INCIDENCE;
        }

        try {
            $effectiveFrom = LocalDate::fromString((string) $payload['effective_from']);
            $effectiveTo = isset($payload['effective_to'])
                ? LocalDate::fromString((string) $payload['effective_to'])
                : null;
        } catch (\InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
            $effectiveFrom = LocalDate::fromString('1900-01-01');
            $effectiveTo = null;
        }

        if ($effectiveTo !== null && $effectiveTo->isBefore($effectiveFrom)) {
            $errors[] = 'effective_to cannot be before effective_from.';
        }

        $requiredFacts = $this->emptyStringList();
        $condition = is_array($payload['when']) ? $payload['when'] : [];
        $this->validateCondition($condition, '$.when', $errors, $requiredFacts);

        $effects = [];
        if (! is_array($payload['effects']) || ! array_is_list($payload['effects'])) {
            $errors[] = 'effects must be a list.';
        } else {
            foreach ($payload['effects'] as $index => $effect) {
                if (! is_array($effect) || ! isset($effect['type'])) {
                    $errors[] = "effects[{$index}] must contain type.";

                    continue;
                }

                try {
                    $type = EffectType::from((string) $effect['type']);
                    unset($effect['type']);
                    $effects[] = new RuleEffect($type, $effect);
                } catch (ValueError) {
                    $errors[] = "Unknown effect type at effects[{$index}].";
                }
            }
        }

        if (! is_array($payload['reason_codes']) || $payload['reason_codes'] === []) {
            $errors[] = 'reason_codes must be a non-empty list.';
        }

        if ($errors !== []) {
            throw new RuleCompilationException(array_values(array_unique($errors)));
        }

        return new CompiledRule(
            id: (string) $payload['rule_id'],
            version: (string) $payload['version'],
            kind: $kind,
            effectiveFrom: $effectiveFrom,
            effectiveTo: $effectiveTo,
            condition: $condition,
            effects: $effects,
            requiredFacts: array_values(array_unique($requiredFacts)),
            sourceId: (string) $payload['source_id'],
            reasonCodes: array_values(array_map('strval', $payload['reason_codes'])),
        );
    }

    /**
     * @param  array<string, mixed>  $condition
     * @param  list<string>  $errors
     * @param  list<string>  $requiredFacts
     */
    private function validateCondition(array $condition, string $path, array &$errors, array &$requiredFacts): void
    {
        foreach (['all', 'any'] as $logical) {
            if (array_key_exists($logical, $condition)) {
                if (! is_array($condition[$logical]) || ! array_is_list($condition[$logical]) || $condition[$logical] === []) {
                    $errors[] = "{$path}.{$logical} must be a non-empty list.";

                    return;
                }

                foreach ($condition[$logical] as $index => $child) {
                    if (! is_array($child)) {
                        $errors[] = "{$path}.{$logical}[{$index}] must be an object.";

                        continue;
                    }
                    $this->validateCondition($child, "{$path}.{$logical}[{$index}]", $errors, $requiredFacts);
                }

                return;
            }
        }

        if (array_key_exists('not', $condition)) {
            if (! is_array($condition['not'])) {
                $errors[] = "{$path}.not must be an object.";

                return;
            }
            $this->validateCondition($condition['not'], "{$path}.not", $errors, $requiredFacts);

            return;
        }

        $leftFact = $condition['fact'] ?? $condition['left_fact'] ?? null;
        if (! is_string($leftFact) || ! isset($condition['operator'])) {
            $errors[] = "{$path} is not a valid predicate.";

            return;
        }

        if (! $this->facts->has($leftFact)) {
            $errors[] = "{$path} references unknown fact {$leftFact}.";

            return;
        }
        $requiredFacts[] = $leftFact;

        try {
            $operator = ComparisonOperator::from((string) $condition['operator']);
        } catch (ValueError) {
            $errors[] = "{$path} contains an unknown operator.";

            return;
        }

        if (isset($condition['right_fact'])) {
            $rightFact = (string) $condition['right_fact'];
            if (! $this->facts->has($rightFact)) {
                $errors[] = "{$path} references unknown fact {$rightFact}.";
            } else {
                $requiredFacts[] = $rightFact;
                if ($this->facts->get($leftFact)->type !== $this->facts->get($rightFact)->type) {
                    $errors[] = "{$path} compares facts with incompatible types.";
                }
            }
        }

        if (! in_array($operator, [ComparisonOperator::EXISTS, ComparisonOperator::NOT_EXISTS], true) && ! array_key_exists('value', $condition) && ! array_key_exists('right_fact', $condition) && ! array_key_exists('catalog', $condition)) {
            $errors[] = "{$path} requires value, right_fact or catalog.";
        }

        $this->validateOperatorType($operator, $this->facts->get($leftFact)->type, $path, $errors);
    }

    /** @param list<string> $errors */
    private function validateOperatorType(ComparisonOperator $operator, FactValueType $type, string $path, array &$errors): void
    {
        $numeric = [FactValueType::INTEGER, FactValueType::DECIMAL];
        $ordered = [ComparisonOperator::GT, ComparisonOperator::GTE, ComparisonOperator::LT, ComparisonOperator::LTE, ComparisonOperator::BETWEEN];
        $dates = [ComparisonOperator::DATE_BEFORE, ComparisonOperator::DATE_ON_OR_BEFORE, ComparisonOperator::DATE_AFTER, ComparisonOperator::DATE_ON_OR_AFTER];

        if (in_array($operator, $ordered, true) && ! in_array($type, $numeric, true)) {
            $errors[] = "{$path} uses numeric operator with {$type->value}.";
        }

        if (in_array($operator, $dates, true) && $type !== FactValueType::DATE) {
            $errors[] = "{$path} uses date operator with {$type->value}.";
        }

        if ($operator === ComparisonOperator::CODE_PREFIX && $type !== FactValueType::CODE) {
            $errors[] = "{$path} uses CODE_PREFIX with {$type->value}.";
        }
    }

    /** @return list<string> */
    private function emptyStringList(): array
    {
        return [];
    }
}
