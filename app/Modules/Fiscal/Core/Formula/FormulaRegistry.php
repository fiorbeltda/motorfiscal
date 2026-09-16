<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Formula;

use InvalidArgumentException;

final class FormulaRegistry
{
    /** @var array<string, Formula> */
    private array $formulas = [];

    /** @param iterable<Formula> $formulas */
    public function __construct(iterable $formulas = [])
    {
        foreach ($formulas as $formula) {
            $this->formulas[$formula->id()] = $formula;
        }
    }

    public function get(string $id): Formula
    {
        return $this->formulas[$id] ?? throw new InvalidArgumentException("Unknown formula: {$id}");
    }
}
