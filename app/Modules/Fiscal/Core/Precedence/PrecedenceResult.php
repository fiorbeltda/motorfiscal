<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Precedence;

final readonly class PrecedenceResult
{
    /** @param list<CandidateDecision> $remainingCandidates */
    public function __construct(
        public PrecedenceStatus $status,
        public ?CandidateDecision $decision,
        public array $remainingCandidates = [],
    ) {}
}
