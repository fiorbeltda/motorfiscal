<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Rules;

use App\Modules\Fiscal\Core\Precedence\RuleRelation;
use App\Modules\Fiscal\Core\Shared\LocalDate;

final readonly class CompiledRule
{
    /**
     * @param  array<string, mixed>  $condition
     * @param  list<RuleEffect>  $effects
     * @param  list<string>  $requiredFacts
     * @param  list<string>  $reasonCodes
     * @param  list<RuleRelation>  $relations
     */
    public function __construct(
        public string $id,
        public string $version,
        public RuleKind $kind,
        public LocalDate $effectiveFrom,
        public ?LocalDate $effectiveTo,
        public array $condition,
        public array $effects,
        public array $requiredFacts,
        public string $sourceId,
        public string $sourceVersion,
        public array $reasonCodes,
        public array $relations = [],
    ) {}

    public function isEffectiveOn(LocalDate $date): bool
    {
        if ($date->isBefore($this->effectiveFrom)) {
            return false;
        }

        return $this->effectiveTo === null || ! $date->isAfter($this->effectiveTo);
    }
}
