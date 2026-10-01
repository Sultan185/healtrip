<?php

namespace App\Directory;

use App\Enums\Specialty;

final readonly class DoctorFilters
{
    public function __construct(
        public ?Specialty $specialty = null,
        public ?string $country = null,
        public ?string $city = null,
        public ?string $language = null,
        public bool $secondOpinionOnly = false,
        public bool $telemedicineOnly = false,
        public ?int $maxFeeUsd = null,
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
            language: $input['language'] ?? null,
            secondOpinionOnly: (bool) ($input['second_opinion_only'] ?? false),
            telemedicineOnly: (bool) ($input['telemedicine_only'] ?? false),
            maxFeeUsd: isset($input['max_fee_usd']) ? (int) $input['max_fee_usd'] : null,
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
            'language' => ['nullable', 'string', 'in:'.implode(',', config('healtrip.languages'))],
            'second_opinion_only' => ['nullable', 'boolean'],
            'telemedicine_only' => ['nullable', 'boolean'],
            'max_fee_usd' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
