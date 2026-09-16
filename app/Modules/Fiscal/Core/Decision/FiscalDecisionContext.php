<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Decision;

use App\Modules\Fiscal\Core\Facts\FactBag;
use App\Modules\Fiscal\Core\Shared\LocalDate;
use DateTimeImmutable;

final readonly class FiscalDecisionContext
{
    /** @param array<string, string> $catalogVersions */
    public function __construct(
        public FactBag $facts,
        public LocalDate $competenceDate,
        public DateTimeImmutable $knowledgeAt,
        public string $ruleBundleId,
        public array $catalogVersions = [],
    ) {}
}
