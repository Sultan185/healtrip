<?php

namespace App\Http\Resources;

use App\Directory\Countries;
use App\Enums\Specialty;
use App\Models\Hospital;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Hospital
 */
class HospitalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => ['en' => $this->name_en, 'ar' => $this->name_ar],
            'city' => ['en' => $this->city_en, 'ar' => $this->city_ar],
            'country' => [
                'code' => $this->country_code,
                'en' => Countries::name($this->country_code, 'en'),
                'ar' => Countries::name($this->country_code, 'ar'),
            ],
            'address' => $this->address_en,
            'phone' => $this->phone,
            'has_emergency' => $this->has_emergency,
            'emergency_phone' => $this->emergency_phone,
            'accreditation' => $this->accreditation,
            'languages' => $this->languages,
            'international_patient_desk' => $this->international_patient_desk,
            'specialties' => $this->whenLoaded('specialtyLinks', fn () => array_map(
                fn (Specialty $specialty) => SpecialtyResource::describe($specialty),
                $this->resource->specialties(),
            )),
        ];
    }
}
