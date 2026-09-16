<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Formula;

interface Formula
{
    public function id(): string;

    /** @param array<string, string> $arguments */
    public function calculate(array $arguments): string;
}
