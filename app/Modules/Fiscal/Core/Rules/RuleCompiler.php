<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Rules;

use App\Modules\Fiscal\Core\Facts\FactDefinition;
use App\Modules\Fiscal\Core\Facts\FactDefinitionRegistry;
use App\Modules\Fiscal\Core\Facts\FactValueType;
use App\Modules\Fiscal\Core\Precedence\RuleRelation;
use App\Modules\Fiscal\Core\Precedence\RuleRelationType;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use ValueError;

final readonly class RuleCompiler
{
    public function __construct(private FactDefinitionRegistry $facts) {}

    public function compile(RawRule $raw): CompiledRule
    {
        $payload = $raw->payload;
        $errors = $this->emptyStringList();

        foreach (['rule_id', 'version', 'kind', 'effective_from', 'when', 'effects', 'source_id', 'source_version', 'reason_codes'] as $field) {
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
                    if (! $type->isSupportedByDecisionEngine()) {
                        $errors[] = "Effect type {$type->value} is reserved but not supported by this engine version.";

                        continue;
                    }
                    unset($effect['type']);
                    $this->validateEffectPayload($type, $effect, "effects[{$index}]", $errors);
                    $effects[] = new RuleEffect($type, $effect);
                } catch (ValueError) {
                    $errors[] = "Unknown effect type at effects[{$index}].";
                }
            }
        }

        if (! is_array($payload['reason_codes']) || $payload['reason_codes'] === []) {
            $errors[] = 'reason_codes must be a non-empty list.';
        }

        $relations = [];
        if (isset($payload['precedence'])) {
            if (! is_array($payload['precedence']) || ! array_is_list($payload['precedence'])) {
                $errors[] = 'precedence must be a list.';
            } else {
                foreach ($payload['precedence'] as $index => $relation) {
                    if (! is_array($relation) || array_is_list($relation)) {
                        $errors[] = "precedence[{$index}] must be an object.";

                        continue;
                    }
                    $targetRuleId = $relation['subordinate_rule_id'] ?? null;
                    $sourceId = $relation['source_id'] ?? null;
                    $sourceVersion = $relation['source_version'] ?? null;
                    if (! is_string($targetRuleId) || $targetRuleId === '' || ! is_string($sourceId) || $sourceId === '' || ! is_string($sourceVersion) || $sourceVersion === '') {
                        $errors[] = "precedence[{$index}] requires subordinate_rule_id, source_id and source_version.";

                        continue;
                    }
                    try {
                        $relationType = RuleRelationType::from((string) ($relation['type'] ?? ''));
                    } catch (ValueError) {
                        $errors[] = "precedence[{$index}] contains an unknown relation type.";

                        continue;
                    }
                    $relations[] = new RuleRelation((string) $payload['rule_id'], $targetRuleId, $relationType, $sourceId, $sourceVersion);
                }
            }
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
            sourceVersion: (string) $payload['source_version'],
            reasonCodes: array_values(array_map('strval', $payload['reason_codes'])),
            relations: $relations,
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

        $definition = $this->facts->get($leftFact);
        $this->validateOperatorType($operator, $definition->type, $path, $errors);
        $this->validateLiteral($condition, $operator, $definition, $path, $errors);
    }

    /**
     * @param  list<string>  $errors
     *
     * @param-out list<string> $errors
     */
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

    /**
     * @param  array<string, mixed>  $condition
     * @param  list<string>  $errors
     *
     * @param-out list<string> $errors
     */
    private function validateLiteral(array $condition, ComparisonOperator $operator, FactDefinition $definition, string $path, array &$errors): void
    {
        if (in_array($operator, [ComparisonOperator::EXISTS, ComparisonOperator::NOT_EXISTS], true) || isset($condition['right_fact'])) {
            return;
        }
        if (in_array($operator, [ComparisonOperator::IN_CATALOG, ComparisonOperator::NOT_IN_CATALOG], true)) {
            if (! isset($condition['catalog']) || ! is_string($condition['catalog']) || $condition['catalog'] === '') {
                $errors[] = "{$path} requires a non-empty catalog.";
            }

            return;
        }
        if (! array_key_exists('value', $condition)) {
            return;
        }
        $literal = $condition['value'];
        if (in_array($operator, [ComparisonOperator::IN, ComparisonOperator::NOT_IN], true)) {
            if (! is_array($literal) || ! array_is_list($literal) || $literal === []) {
                $errors[] = "{$path}.value must be a non-empty list for {$operator->value}.";

                return;
            }
            foreach ($literal as $index => $item) {
                if (! $definition->accepts($item)) {
                    $errors[] = "{$path}.value[{$index}] is incompatible with {$definition->type->value}.";
                }
            }

            return;
        }
        if ($operator === ComparisonOperator::BETWEEN) {
            if (! is_array($literal) || ! array_is_list($literal) || count($literal) !== 2) {
                $errors[] = "{$path}.value must contain exactly two bounds for BETWEEN.";

                return;
            }
            foreach ($literal as $index => $item) {
                if (! $definition->accepts($item)) {
                    $errors[] = "{$path}.value[{$index}] is incompatible with {$definition->type->value}.";
                }
            }

            return;
        }
        if (! $definition->accepts($literal)) {
            $errors[] = "{$path}.value is incompatible with {$definition->type->value}.";
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $errors
     *
     * @param-out list<string> $errors
     */
    private function validateEffectPayload(EffectType $type, array $payload, string $path, array &$errors): void
    {
        $required = match ($type) {
            EffectType::SELECT_STRATEGY => 'strategy',
            EffectType::SET_DECISION_VALUE => 'path',
            EffectType::ADD_CANDIDATE, EffectType::EXCLUDE_CANDIDATE => 'candidate',
            EffectType::APPLY_TREATMENT => 'treatment',
            EffectType::SET_RESPONSIBLE_PARTY => 'party',
            EffectType::SET_TEMPORAL_ANCHOR => 'anchor',
            EffectType::REQUIRE_DETAIL_GROUP => 'group',
            EffectType::ADD_OBLIGATION_IMPACT => 'impact',
            EffectType::ADD_WARNING => 'warning',
            EffectType::REQUEST_MANUAL_REVIEW => 'reason',
            EffectType::SELECT_FORMULA => 'formula',
        };
        if (! isset($payload[$required]) || ! is_string($payload[$required]) || $payload[$required] === '') {
            $errors[] = "{$path} requires non-empty {$required}.";
        }
        if ($type === EffectType::SET_DECISION_VALUE && ! array_key_exists('value', $payload)) {
            $errors[] = "{$path} requires value.";
        }
        if ($type === EffectType::SELECT_FORMULA) {
            if (! isset($payload['arguments']) || ! is_array($payload['arguments']) || array_is_list($payload['arguments'])) {
                $errors[] = "{$path} requires non-empty formula argument bindings.";

                return;
            }
            foreach ($payload['arguments'] as $argument => $source) {
                if (! is_string($argument) || $argument === '' || ! is_string($source) || $source === '') {
                    $errors[] = "{$path} contains an invalid formula argument binding.";
                }
            }
        }
    }
}
