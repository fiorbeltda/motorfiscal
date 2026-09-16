<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Questions;

final readonly class QuestionPlan
{
    /**
     * @param  list<QuestionDefinition>  $questions
     * @param  list<string>  $unmappedFacts
     */
    public function __construct(
        public array $questions,
        public array $unmappedFacts,
    ) {}
}
