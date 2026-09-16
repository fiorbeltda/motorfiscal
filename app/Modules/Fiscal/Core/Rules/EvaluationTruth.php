<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Rules;

enum EvaluationTruth: string
{
    case TRUE = 'TRUE';
    case FALSE = 'FALSE';
    case UNKNOWN = 'UNKNOWN';
    case ERROR = 'ERROR';

    /** @param list<self> $values */
    public static function all(array $values): self
    {
        if (in_array(self::ERROR, $values, true)) {
            return self::ERROR;
        }

        if (in_array(self::FALSE, $values, true)) {
            return self::FALSE;
        }

        return in_array(self::UNKNOWN, $values, true) ? self::UNKNOWN : self::TRUE;
    }

    /** @param list<self> $values */
    public static function any(array $values): self
    {
        if (in_array(self::ERROR, $values, true)) {
            return self::ERROR;
        }

        if (in_array(self::TRUE, $values, true)) {
            return self::TRUE;
        }

        return in_array(self::UNKNOWN, $values, true) ? self::UNKNOWN : self::FALSE;
    }

    public function negate(): self
    {
        return match ($this) {
            self::TRUE => self::FALSE,
            self::FALSE => self::TRUE,
            self::UNKNOWN => self::UNKNOWN,
            self::ERROR => self::ERROR,
        };
    }
}
