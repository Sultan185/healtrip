<?php

namespace App\Directory;

use App\Enums\Specialty;

final readonly class HospitalFilters
{
    public function __construct(
        public ?Specialty $specialty = null,
        public ?string $country = null,
        public ?string $city = null,
        public bool $emergencyOnly = false,
    ) {}

    /**
     * @param  array<string, mixed>  $input  Already validated.
     */
    public static function fromArray(array $input): self
    {
        return new self(
            specialty: isset($input['specialty']) ? Specialty::from($input['specialty']) : null,
            country: $input['country'] ?? null,
            city: $input['city'] ?? null,
            emergencyOnly: (bool) ($input['emergency_only'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'specialty' => ['nullable', 'string', 'in:'.implode(',', Specialty::values())],
            'country' => ['nullable', 'string', 'max:60'],
            'city' => ['nullable', 'string', 'max:60'],
            'emergency_only' => ['nullable', 'boolean'],
        ];
    }
}
