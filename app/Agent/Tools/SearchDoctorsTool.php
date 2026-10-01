<?php

namespace App\Agent\Tools;

use App\Directory\DoctorFilters;
use App\Directory\ProviderSearch;
use App\Enums\Specialty;
use App\Models\Doctor;

class SearchDoctorsTool implements Tool
{
    public function __construct(private readonly ProviderSearch $search) {}

    public function name(): string
    {
        return 'search_doctors';
    }

    public function definition(): array
    {
        return [
            'name' => $this->name(),
            'description' => "Search HealTrip's provider directory for doctors. This is the only source of doctor information: "
                .'every name, fee, language and availability date you mention must come from a result of this tool in the current conversation. '
                .'Returns at most '.config('healtrip.agent.max_search_results').' doctors, best match first (rating, then experience), plus `total_matches`. '
                .'Pass null for any filter the patient has not expressed a preference about; do not guess a country or a language. '
                .'If nothing matches, `doctors` is empty and `coverage` lists where the directory does have doctors, so you can offer a real alternative.',
            'strict' => true,
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'specialty' => [
                        'anyOf' => [['type' => 'string', 'enum' => Specialty::values()], ['type' => 'null']],
                        'description' => 'Medical specialty to search in.',
                    ],
                    'country' => [
                        'type' => ['string', 'null'],
                        'description' => 'Country the patient wants care in: ISO code or name, e.g. "TR", "Egypt", "الإمارات".',
                    ],
                    'city' => [
                        'type' => ['string', 'null'],
                        'description' => 'City the patient wants care in, e.g. "Istanbul", "القاهرة".',
                    ],
                    'language' => [
                        'anyOf' => [['type' => 'string', 'enum' => config('healtrip.languages')], ['type' => 'null']],
                        'description' => 'A language the doctor must speak (ISO 639-1 code).',
                    ],
                    'second_opinion_only' => [
                        'type' => 'boolean',
                        'description' => 'True to return only doctors who offer second-opinion reviews.',
                    ],
                    'telemedicine_only' => [
                        'type' => 'boolean',
                        'description' => 'True to return only doctors who offer remote consultations.',
                    ],
                    'max_fee_usd' => [
                        'type' => ['integer', 'null'],
                        'description' => 'Upper limit for the consultation fee in US dollars.',
                    ],
                ],
                'required' => ['specialty', 'country', 'city', 'language', 'second_opinion_only', 'telemedicine_only', 'max_fee_usd'],
                'additionalProperties' => false,
            ],
        ];
    }

    public function rules(): array
    {
        return DoctorFilters::rules();
    }

    public function handle(array $input, ToolContext $context): ToolOutcome
    {
        $filters = DoctorFilters::fromArray($input);
        $limit = (int) config('healtrip.agent.max_search_results');
        $result = $this->search->doctors($filters, $limit);

        $payload = [
            // Echoed so the model reasons about the search it actually ran.
            'filters_applied' => array_filter($input, fn (mixed $value) => $value !== null && $value !== false),
            'total_matches' => $result->total,
            'doctors' => $result->items->map(fn (Doctor $doctor) => self::present($doctor))->all(),
        ];

        if ($result->total > 0 && $filters->specialty === null) {
            $payload['warning'] = 'No specialty filter was applied, so these doctors practise different specialties. '
                ."Check each doctor's `specialty` before describing them, or search again with the specialty the patient needs.";
        }

        if ($result->total === 0) {
            $payload['note'] = 'No doctor in the HealTrip directory matches these filters. Do not suggest one anyway: '
                .'tell the patient, and offer to relax a filter or one of the real alternatives in `coverage`.';
            $payload['coverage'] = $this->search->doctorCoverage($filters->specialty);
        } elseif ($result->total > $result->items->count()) {
            $payload['note'] = "Showing the top {$result->items->count()} of {$result->total}. Add filters to narrow the search.";
        }

        return new ToolOutcome(
            payload: $payload,
            doctorIds: array_values($result->items->map(fn (Doctor $doctor) => $doctor->id)->all()),
            // A doctor's result names their hospital, so that hospital is now
            // a fact the model has legitimately seen.
            hospitalIds: array_values(array_unique($result->items->map(fn (Doctor $doctor) => $doctor->hospital_id)->all())),
            stats: ['returned' => $result->items->count(), 'total' => $result->total],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(Doctor $doctor): array
    {
        return [
            'doctor_id' => $doctor->id,
            'name' => $doctor->name_en,
            'name_ar' => $doctor->name_ar,
            'specialty' => $doctor->specialty->value,
            'focus' => $doctor->focus_en,
            'years_experience' => $doctor->years_experience,
            'languages' => $doctor->languages,
            'consultation_fee_usd' => $doctor->consultation_fee_usd,
            'offers_second_opinion' => $doctor->offers_second_opinion,
            'offers_telemedicine' => $doctor->offers_telemedicine,
            'rating' => (float) $doctor->rating,
            'next_available_on' => $doctor->next_available_on->toDateString(),
            'hospital' => [
                'hospital_id' => $doctor->hospital->id,
                'name' => $doctor->hospital->name_en,
                'name_ar' => $doctor->hospital->name_ar,
                'city' => $doctor->hospital->city_en,
                'country' => $doctor->hospital->country_code,
                'has_emergency' => $doctor->hospital->has_emergency,
            ],
        ];
    }
}
