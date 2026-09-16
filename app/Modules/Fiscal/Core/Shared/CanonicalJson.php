<?php

declare(strict_types=1);

namespace App\Modules\Fiscal\Core\Shared;

final class CanonicalJson
{
    /** @param array<string|int, mixed> $value */
    public static function encode(array $value): string
    {
        $normalized = self::normalize($value);

        return json_encode(
            $normalized,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );
    }

    /** @param array<string|int, mixed> $value */
    public static function hash(array $value): string
    {
        return hash('sha256', self::encode($value));
    }

    /**
     * @param  array<string|int, mixed>  $value
     * @return array<string|int, mixed>
     */
    private static function normalize(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::normalize($item);
            }
        }

        return $value;
    }
}
