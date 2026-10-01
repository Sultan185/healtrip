<?php

namespace App\Http\Resources;

use App\Directory\Countries;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Doctor
 */
class DoctorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hospital = $this->hospital;

        return [
            'id' => $this->id,
            'name' => ['en' => $this->name_en, 'ar' => $this->name_ar],
            'specialty' => SpecialtyResource::describe($this->specialty),
            'focus' => ['en' => $this->focus_en, 'ar' => $this->focus_ar],
            'years_experience' => $this->years_experience,
            'languages' => $this->languages,
            'consultation_fee_usd' => $this->consultation_fee_usd,
            'offers_second_opinion' => $this->offers_second_opinion,
            'offers_telemedicine' => $this->offers_telemedicine,
            'rating' => (float) $this->rating,
            'next_available_on' => $this->next_available_on->toDateString(),
            'hospital' => [
                'id' => $hospital->id,
                'name' => ['en' => $hospital->name_en, 'ar' => $hospital->name_ar],
                'city' => ['en' => $hospital->city_en, 'ar' => $hospital->city_ar],
                'country' => [
                    'code' => $hospital->country_code,
                    'en' => Countries::name($hospital->country_code, 'en'),
                    'ar' => Countries::name($hospital->country_code, 'ar'),
                ],
            ],
        ];
    }
}
