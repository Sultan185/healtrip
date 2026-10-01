<?php

namespace App\Http\Resources;

use App\Models\Recommendation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The recommendation card. The model chose the care path and wrote the
 * summary; every provider field below is read from the database.
 *
 * @mixin Recommendation
 */
class RecommendationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['doctors.hospital', 'hospitals.specialtyLinks']);

        return [
            'id' => $this->id,
            'urgency' => $this->urgency->value,
            'care_path' => $this->care_path->value,
            'specialty' => $this->specialty ? SpecialtyResource::describe($this->specialty) : null,
            'summary' => $this->summary,
            'doctors' => DoctorResource::collection($this->doctors)->resolve($request),
            'hospitals' => HospitalResource::collection($this->hospitals)->resolve($request),
        ];
    }
}
