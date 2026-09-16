<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Rules;

use RuntimeException;

final class RuleCompilationException extends RuntimeException
{
    /** @param list<string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }
}
