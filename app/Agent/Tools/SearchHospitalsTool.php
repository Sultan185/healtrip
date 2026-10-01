<?php

namespace App\Agent\Tools;

use App\Directory\HospitalFilters;
use App\Directory\ProviderSearch;
use App\Enums\Specialty;
use App\Models\Hospital;

class SearchHospitalsTool implements Tool
{
    public function __construct(private readonly ProviderSearch $search) {}

    public function name(): string
    {
        return 'search_hospitals';
    }

    public function definition(): array
    {
        return [
            'name' => $this->name(),
            'description' => "Search HealTrip's provider directory for hospitals, for example to find one with an emergency department "
                ."in the patient's city, or one that offers a given specialty. This is the only source of hospital information: every "
                .'hospital you mention must come from a result of this tool (or of search_doctors) in the current conversation. '
                .'Returns at most '.config('healtrip.agent.max_search_results').' hospitals plus `total_matches`. '
                .'Pass null for any filter the patient has not expressed a preference about. '
                .'If nothing matches, `hospitals` is empty; say so rather than naming a hospital from memory.',
            'strict' => true,
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'specialty' => [
                        'anyOf' => [['type' => 'string', 'enum' => Specialty::values()], ['type' => 'null']],
                        'description' => 'Only hospitals with a department in this specialty.',
                    ],
                    'country' => [
                        'type' => ['string', 'null'],
                        'description' => 'ISO code or name, e.g. "TR", "Egypt", "الإمارات".',
                    ],
                    'city' => [
                        'type' => ['string', 'null'],
                        'description' => 'City name, e.g. "Dubai", "الرياض".',
                    ],
                    'emergency_only' => [
                        'type' => 'boolean',
                        'description' => 'True to return only hospitals with a 24/7 emergency department.',
                    ],
                ],
                'required' => ['specialty', 'country', 'city', 'emergency_only'],
                'additionalProperties' => false,
            ],
        ];
    }

    public function rules(): array
    {
        return HospitalFilters::rules();
    }

    public function handle(array $input, ToolContext $context): ToolOutcome
    {
        $filters = HospitalFilters::fromArray($input);
        $limit = (int) config('healtrip.agent.max_search_results');
        $result = $this->search->hospitals($filters, $limit);

        $payload = [
            'total_matches' => $result->total,
            'hospitals' => $result->items->map(fn (Hospital $hospital) => self::present($hospital))->all(),
        ];

        if ($result->total === 0) {
            $payload['note'] = 'No hospital in the HealTrip directory matches these filters. Do not name one anyway: '
                .'tell the patient, and offer to search another city or country the directory covers.';
            $payload['countries_covered'] = array_keys(config('healtrip.countries'));
        }

        return new ToolOutcome(
            payload: $payload,
            hospitalIds: array_values($result->items->map(fn (Hospital $hospital) => $hospital->id)->all()),
            stats: ['returned' => $result->items->count(), 'total' => $result->total],
        );
    }

    /**
     * Contact details are deliberately left out: the patient gets phone
     * numbers from the card, which reads them from the database, so the model
     * never has a reason to write one.
     *
     * @return array<string, mixed>
     */
    public static function present(Hospital $hospital): array
    {
        return [
            'hospital_id' => $hospital->id,
            'name' => $hospital->name_en,
            'name_ar' => $hospital->name_ar,
            'city' => $hospital->city_en,
            'country' => $hospital->country_code,
            'has_emergency' => $hospital->has_emergency,
            'accreditation' => $hospital->accreditation,
            'languages' => $hospital->languages,
            'international_patient_desk' => $hospital->international_patient_desk,
            'specialties' => array_map(fn (Specialty $specialty) => $specialty->value, $hospital->specialties()),
        ];
    }
}
