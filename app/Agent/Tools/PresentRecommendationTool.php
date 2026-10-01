<?php

namespace App\Agent\Tools;

use App\Agent\Guardrails\OutputGuard;
use App\Enums\CarePath;
use App\Enums\Specialty;
use App\Enums\Urgency;
use App\Models\Doctor;
use App\Models\Hospital;
use App\Models\Recommendation;
use Illuminate\Support\Facades\DB;

/**
 * How the agent recommends anything. The model does not describe providers;
 * it passes IDs, and the UI renders the card from the database. An ID that no
 * search returned in this conversation is rejected, so a provider the model
 * imagined has no way onto the screen.
 */
class PresentRecommendationTool implements Tool
{
    public function __construct(private readonly OutputGuard $guard) {}

    public function name(): string
    {
        return 'present_recommendation';
    }

    public function definition(): array
    {
        $max = (int) config('healtrip.agent.max_recommended');

        return [
            'name' => $this->name(),
            'description' => 'Show the patient your recommended next step as a card in the chat, optionally with up to '
                ."{$max} doctors and {$max} hospitals. The card is rendered from the HealTrip database using the IDs you pass, "
                .'so pass only IDs that a search tool returned in this conversation; any other ID is rejected. '
                .'Call it once you know enough to recommend a care path, and again if the situation or the patient\'s preferences change. '
                .'In an emergency call it straight away with urgency "emergency" and care_path "emergency_care": attach hospitals '
                ."with an emergency department if you know the patient's location, otherwise pass empty lists.",
            'strict' => true,
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'urgency' => [
                        'type' => 'string',
                        'enum' => Urgency::values(),
                        'description' => 'emergency: needs emergency care now. urgent: should be seen within about a day. routine: can be scheduled.',
                    ],
                    'care_path' => [
                        'type' => 'string',
                        'enum' => CarePath::values(),
                        'description' => 'The kind of care you are recommending as the next step.',
                    ],
                    'specialty' => [
                        'anyOf' => [['type' => 'string', 'enum' => Specialty::values()], ['type' => 'null']],
                        'description' => 'The specialty the patient should see, if one applies.',
                    ],
                    'summary' => [
                        'type' => 'string',
                        'description' => "One or two plain sentences in the patient's language saying what to do next and why. "
                            .'No provider names, phone numbers or links: the card shows those.',
                    ],
                    'doctor_ids' => [
                        'type' => 'array',
                        'items' => ['type' => 'integer'],
                        'description' => '`doctor_id` values from search_doctors results, best match first. May be empty.',
                    ],
                    'hospital_ids' => [
                        'type' => 'array',
                        'items' => ['type' => 'integer'],
                        'description' => '`hospital_id` values from search results, best match first. May be empty.',
                    ],
                ],
                'required' => ['urgency', 'care_path', 'specialty', 'summary', 'doctor_ids', 'hospital_ids'],
                'additionalProperties' => false,
            ],
        ];
    }

    public function rules(): array
    {
        $max = (int) config('healtrip.agent.max_recommended');

        return [
            'urgency' => ['required', 'string', 'in:'.implode(',', Urgency::values())],
            'care_path' => ['required', 'string', 'in:'.implode(',', CarePath::values())],
            'specialty' => ['nullable', 'string', 'in:'.implode(',', Specialty::values())],
            'summary' => ['required', 'string', 'max:600'],
            'doctor_ids' => ['present', 'array', "max:{$max}"],
            'doctor_ids.*' => ['integer', 'distinct'],
            'hospital_ids' => ['present', 'array', "max:{$max}"],
            'hospital_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function handle(array $input, ToolContext $context): ToolOutcome
    {
        $urgency = Urgency::from($input['urgency']);
        $carePath = CarePath::from($input['care_path']);
        /** @var list<int> $doctorIds */
        $doctorIds = array_map(intval(...), $input['doctor_ids']);
        /** @var list<int> $hospitalIds */
        $hospitalIds = array_map(intval(...), $input['hospital_ids']);

        if ($urgency === Urgency::Emergency && $carePath !== CarePath::EmergencyCare) {
            return ToolOutcome::error('inconsistent_recommendation', 'An emergency must be sent to emergency care: use care_path "emergency_care" with urgency "emergency".');
        }

        $unknownDoctors = $context->ledger->unknownDoctors($doctorIds);
        $unknownHospitals = $context->ledger->unknownHospitals($hospitalIds);

        if ($unknownDoctors !== [] || $unknownHospitals !== []) {
            return ToolOutcome::error(
                'ungrounded_ids',
                'These IDs were not returned by a directory search in this conversation, so they cannot be recommended. '
                .'Search first, then pass IDs exactly as they appear in the results.',
                ['unknown_doctor_ids' => $unknownDoctors, 'unknown_hospital_ids' => $unknownHospitals],
            );
        }

        $doctors = Doctor::query()->findMany($doctorIds);
        $hospitals = Hospital::query()->findMany($hospitalIds);

        // A card headed "Oncology" must not list cardiologists: the model
        // chose the heading, the database knows each doctor's specialty.
        if (isset($input['specialty'])) {
            $mismatched = $doctors->filter(fn (Doctor $doctor) => $doctor->specialty->value !== $input['specialty']);

            if ($mismatched->isNotEmpty()) {
                return ToolOutcome::error(
                    'specialty_mismatch',
                    "These doctors do not practise {$input['specialty']}, so they cannot be recommended for it. "
                    .'Search with the specialty filter, and if nothing matches, tell the patient so.',
                    ['actual_specialties' => $mismatched->mapWithKeys(fn (Doctor $doctor) => [$doctor->id => $doctor->specialty->value])->all()],
                );
            }
        }

        $verdict = $this->guard->inspect($input['summary'], $context->ledger);

        if (! $verdict->passed()) {
            return ToolOutcome::error(
                'unverified_summary',
                'The summary contains details that are not allowed in it or could not be verified against search results ('
                .$verdict->describe().'). Rewrite it without provider names, phone numbers or links.',
            );
        }

        $recommendation = DB::transaction(function () use ($context, $urgency, $carePath, $input, $doctorIds, $hospitalIds) {
            $recommendation = Recommendation::create([
                'conversation_id' => $context->conversation->id,
                'turn' => $context->turn,
                'urgency' => $urgency,
                'care_path' => $carePath,
                'specialty' => $input['specialty'] ?? null,
                'summary' => $input['summary'],
            ]);

            foreach ($doctorIds as $position => $id) {
                $recommendation->doctors()->attach($id, ['position' => $position]);
            }

            foreach ($hospitalIds as $position => $id) {
                $recommendation->hospitals()->attach($id, ['position' => $position]);
            }

            $context->conversation->escalateTo($urgency);

            return $recommendation;
        });

        // From here on the assistant may refer to these providers by name.
        $context->ledger->markPresented($doctors, $hospitals);

        return new ToolOutcome(
            payload: [
                'status' => 'displayed',
                'note' => 'The patient now sees this recommendation as a card with full provider details. Do not repeat those details in your reply.',
            ],
            recommendation: $recommendation,
        );
    }
}
