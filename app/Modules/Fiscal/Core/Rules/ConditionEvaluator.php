<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Rules;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\DecimalValue;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use Throwable;
use ValueError;

final readonly class ConditionEvaluator
{
    public function __construct(private CatalogMembership $catalogs = new InMemoryCatalogMembership) {}

    /** @param array<string, mixed> $condition */
    public function evaluate(array $condition, FactBag $facts): ConditionEvaluation
    {
        try {
            return $this->evaluateNode($condition, $facts);
        } catch (Throwable $exception) {
            return new ConditionEvaluation(EvaluationTruth::ERROR, errors: [$exception->getMessage()]);
        }
    }

    /** @param array<string, mixed> $condition */
    private function evaluateNode(array $condition, FactBag $facts): ConditionEvaluation
    {
        foreach (['all', 'any'] as $logical) {
            if (isset($condition[$logical]) && is_array($condition[$logical])) {
                $children = array_values(array_map(
                    fn (mixed $child): ConditionEvaluation => $this->evaluateNode(is_array($child) ? $child : [], $facts),
                    $condition[$logical],
                ));

                $truths = array_values(array_map(fn (ConditionEvaluation $result): EvaluationTruth => $result->truth, $children));

                return new ConditionEvaluation(
                    $logical === 'all' ? EvaluationTruth::all($truths) : EvaluationTruth::any($truths),
                    $this->mergeMissingFacts($children),
                    $this->mergeErrors($children),
                );
            }
        }

        if (isset($condition['not']) && is_array($condition['not'])) {
            $result = $this->evaluateNode($condition['not'], $facts);

            return new ConditionEvaluation($result->truth->negate(), $result->missingFacts, $result->errors);
        }

        return $this->evaluatePredicate($condition, $facts);
    }

    /** @param array<string, mixed> $predicate */
    private function evaluatePredicate(array $predicate, FactBag $facts): ConditionEvaluation
    {
        $factKey = (string) ($predicate['fact'] ?? $predicate['left_fact'] ?? '');

        try {
            $operator = ComparisonOperator::from((string) ($predicate['operator'] ?? ''));
        } catch (ValueError) {
            return new ConditionEvaluation(EvaluationTruth::ERROR, errors: ['Unknown comparison operator.']);
        }

        if ($operator === ComparisonOperator::EXISTS) {
            return new ConditionEvaluation($facts->has($factKey) ? EvaluationTruth::TRUE : EvaluationTruth::FALSE);
        }

        if ($operator === ComparisonOperator::NOT_EXISTS) {
            return new ConditionEvaluation($facts->has($factKey) ? EvaluationTruth::FALSE : EvaluationTruth::TRUE);
        }

        if (! $facts->has($factKey)) {
            return new ConditionEvaluation(EvaluationTruth::UNKNOWN, [$factKey]);
        }

        $rightFact = isset($predicate['right_fact']) ? (string) $predicate['right_fact'] : null;
        if ($rightFact !== null && ! $facts->has($rightFact)) {
            return new ConditionEvaluation(EvaluationTruth::UNKNOWN, [$rightFact]);
        }

        $left = $facts->value($factKey);
        $right = $rightFact !== null ? $facts->value($rightFact) : ($predicate['value'] ?? null);

        $matched = match ($operator) {
            ComparisonOperator::EQ => $left === $right,
            ComparisonOperator::NEQ => $left !== $right,
            ComparisonOperator::IN => is_array($right) && in_array($left, $right, true),
            ComparisonOperator::NOT_IN => is_array($right) && ! in_array($left, $right, true),
            ComparisonOperator::GT => $this->compareNumeric($left, $right) > 0,
            ComparisonOperator::GTE => $this->compareNumeric($left, $right) >= 0,
            ComparisonOperator::LT => $this->compareNumeric($left, $right) < 0,
            ComparisonOperator::LTE => $this->compareNumeric($left, $right) <= 0,
            ComparisonOperator::BETWEEN => $this->between($left, $right),
            ComparisonOperator::DATE_BEFORE => LocalDate::fromString((string) $left)->isBefore(LocalDate::fromString((string) $right)),
            ComparisonOperator::DATE_ON_OR_BEFORE => ! LocalDate::fromString((string) $left)->isAfter(LocalDate::fromString((string) $right)),
            ComparisonOperator::DATE_AFTER => LocalDate::fromString((string) $left)->isAfter(LocalDate::fromString((string) $right)),
            ComparisonOperator::DATE_ON_OR_AFTER => ! LocalDate::fromString((string) $left)->isBefore(LocalDate::fromString((string) $right)),
            ComparisonOperator::CODE_PREFIX => is_string($left) && is_string($right) && str_starts_with($left, $right),
            ComparisonOperator::IN_CATALOG => $this->catalogs->contains((string) ($predicate['catalog'] ?? ''), (string) $left),
            ComparisonOperator::NOT_IN_CATALOG => ! $this->catalogs->contains((string) ($predicate['catalog'] ?? ''), (string) $left),
        };

        return new ConditionEvaluation($matched ? EvaluationTruth::TRUE : EvaluationTruth::FALSE);
    }

    private function compareNumeric(mixed $left, mixed $right): int
    {
        return DecimalValue::fromString((string) $left)->compareTo(DecimalValue::fromString((string) $right));
    }

    private function between(mixed $left, mixed $right): bool
    {
        if (! is_array($right) || count($right) !== 2) {
            throw new \InvalidArgumentException('BETWEEN requires exactly two limits.');
        }

        return $this->compareNumeric($left, $right[0]) >= 0 && $this->compareNumeric($left, $right[1]) <= 0;
    }

    /**
     * @param  list<ConditionEvaluation>  $results
     * @return list<string>
     */
    private function mergeMissingFacts(array $results): array
    {
        $merged = [];

        foreach ($results as $result) {
            $merged = [...$merged, ...$result->missingFacts];
        }

        return array_values(array_unique($merged));
    }

    /**
     * @param  list<ConditionEvaluation>  $results
     * @return list<string>
     */
    private function mergeErrors(array $results): array
    {
        $merged = [];

        foreach ($results as $result) {
            $merged = [...$merged, ...$result->errors];
        }

        return array_values(array_unique($merged));
    }
}
