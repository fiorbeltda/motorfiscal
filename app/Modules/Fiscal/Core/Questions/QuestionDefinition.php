<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Questions;

final readonly class QuestionDefinition
{
    /** @param list<string> $options */
    public function __construct(
        public string $factKey,
        public string $prompt,
        public int $rank,
        public string $answerType,
        public array $options = [],
    ) {}
}
