<?php

namespace App\Http\Resources;

use App\Enums\Specialty;

final class SpecialtyResource
{
    /**
     * @return array{slug: string, en: string, ar: string}
     */
    public static function describe(Specialty $specialty): array
    {
        return [
            'slug' => $specialty->value,
            'en' => $specialty->labelEn(),
            'ar' => $specialty->labelAr(),
        ];
    }
}
