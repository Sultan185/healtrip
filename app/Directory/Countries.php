<?php

namespace App\Directory;

use Illuminate\Support\Str;

/**
 * Maps whatever a caller wrote for a country ("TR", "Turkey", "تركيا") onto
 * the ISO code the directory stores.
 */
final class Countries
{
    public static function resolve(string $input): ?string
    {
        $needle = self::normalize($input);

        /** @var array<string, array{en: string, ar: string, aliases: list<string>}> $countries */
        $countries = config('healtrip.countries');

        foreach ($countries as $code => $country) {
            $names = [$code, $country['en'], $country['ar'], ...$country['aliases']];

            if (in_array($needle, array_map(self::normalize(...), $names), true)) {
                return $code;
            }
        }

        return null;
    }

    public static function name(string $code, string $locale = 'en'): string
    {
        return config("healtrip.countries.$code.$locale") ?? $code;
    }

    private static function normalize(string $value): string
    {
        return mb_strtolower(Str::ascii(trim($value)));
    }
}
