<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Precedence;

final readonly class RuleRelation
{
    public function __construct(
        public string $dominantRuleId,
        public string $subordinateRuleId,
        public RuleRelationType $type,
        public string $sourceId,
        public string $sourceVersion = '',
    ) {}
}
