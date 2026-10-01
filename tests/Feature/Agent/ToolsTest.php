<?php

use App\Agent\Guardrails\GroundingLedger;
use App\Agent\Tools\ToolContext;
use App\Agent\Tools\ToolRegistry;
use App\Models\Doctor;
use App\Models\Hospital;
use App\Models\Recommendation;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->tools = app(ToolRegistry::class);
    $this->conversation = conversation();
    $this->context = new ToolContext($this->conversation, 1, new GroundingLedger);
});

function recommendation(array $overrides = []): array
{
    return [
        'urgency' => 'routine',
        'care_path' => 'specialist_visit',
        'specialty' => 'cardiology',
        'summary' => 'A cardiology consultation is a sensible next step.',
        'doctor_ids' => [],
        'hospital_ids' => [],
        ...$overrides,
    ];
}

it('exposes a stable, strictly-typed tool list', function () {
    $definitions = $this->tools->definitions();

    expect(array_column($definitions, 'name'))->toBe(['present_recommendation', 'search_doctors', 'search_hospitals'])
        ->and($definitions)->toEqual($this->tools->definitions());

    foreach ($definitions as $definition) {
        expect($definition['strict'])->toBeTrue()
            ->and($definition['input_schema']['additionalProperties'])->toBeFalse()
            ->and($definition['input_schema']['required'])->toEqualCanonicalizing(array_keys($definition['input_schema']['properties']));
    }
});

it('returns matching doctors with the IDs that make them recommendable', function () {
    $outcome = $this->tools->execute('search_doctors', doctorSearch(['specialty' => 'cardiology', 'city' => 'Istanbul']), $this->context);

    expect($outcome->isError)->toBeFalse()
        ->and($outcome->payload['total_matches'])->toBe(2)
        ->and(array_column($outcome->payload['doctors'], 'name'))->toBe(['Elif Kaya', 'Kerem Yildiz'])
        ->and($outcome->doctorIds)->toBe(array_column($outcome->payload['doctors'], 'doctor_id'))
        ->and($outcome->payload['doctors'][0])->not->toHaveKey('phone');
});

it('says so, and offers real alternatives, when nothing matches', function () {
    $outcome = $this->tools->execute('search_doctors', doctorSearch(['specialty' => 'oncology', 'country' => 'Saudi Arabia']), $this->context);

    expect($outcome->isError)->toBeFalse()
        ->and($outcome->payload['doctors'])->toBe([])
        ->and($outcome->payload['note'])->toContain('Do not suggest one anyway')
        ->and(array_column($outcome->payload['coverage'], 'country'))->not->toContain('SA')
        ->and($outcome->doctorIds)->toBe([]);
});

it('never gives the model a provider phone number', function () {
    $outcome = $this->tools->execute('search_hospitals', ['specialty' => null, 'country' => null, 'city' => null, 'emergency_only' => true], $this->context);

    expect(json_encode($outcome->payload))->not->toContain('555');
});

it('rejects invalid input with a message the model can act on', function () {
    $outcome = $this->tools->execute('search_doctors', doctorSearch(['specialty' => 'astrology']), $this->context);

    expect($outcome->isError)->toBeTrue()
        ->and($outcome->payload['error'])->toBe('invalid_input')
        ->and($outcome->payload['problems'])->not->toBeEmpty();
});

it('rejects a tool that does not exist', function () {
    $outcome = $this->tools->execute('book_appointment', [], $this->context);

    expect($outcome->isError)->toBeTrue()->and($outcome->payload['error'])->toBe('unknown_tool');
});

it('refuses to recommend a provider that no search returned', function () {
    $realButNeverRetrieved = Doctor::firstOrFail()->id;

    $outcome = $this->tools->execute('present_recommendation', recommendation(['doctor_ids' => [$realButNeverRetrieved, 99999]]), $this->context);

    expect($outcome->isError)->toBeTrue()
        ->and($outcome->payload['error'])->toBe('ungrounded_ids')
        ->and($outcome->payload['unknown_doctor_ids'])->toBe([$realButNeverRetrieved, 99999])
        ->and(Recommendation::count())->toBe(0);
});

it('stores a recommendation for providers a search did return', function () {
    $search = $this->tools->execute('search_doctors', doctorSearch(['specialty' => 'cardiology', 'city' => 'Cairo']), $this->context);
    $this->context->ledger->record($search->doctorIds, $search->hospitalIds);

    $outcome = $this->tools->execute('present_recommendation', recommendation([
        'doctor_ids' => $search->doctorIds,
        'hospital_ids' => $search->hospitalIds,
    ]), $this->context);

    expect($outcome->isError)->toBeFalse()
        ->and($outcome->recommendation->doctors->modelKeys())->toBe($search->doctorIds)
        ->and($outcome->recommendation->hospitals->first()->name_en)->toBe('Nile Valley Heart Center')
        ->and($this->conversation->fresh()->urgency->value)->toBe('routine');
});

it('will not file an emergency under any other care path', function () {
    $outcome = $this->tools->execute('present_recommendation', recommendation(['urgency' => 'emergency']), $this->context);

    expect($outcome->isError)->toBeTrue()->and($outcome->payload['error'])->toBe('inconsistent_recommendation');
});

it('rejects a summary that smuggles in an unverified provider', function () {
    $outcome = $this->tools->execute('present_recommendation', recommendation([
        'summary' => 'See Dr. John Smith at Mayo Clinic, call +1 507 284 2511.',
    ]), $this->context);

    expect($outcome->isError)->toBeTrue()->and($outcome->payload['error'])->toBe('unverified_summary');
});

it('enforces provider references at the database level too', function () {
    $recommendation = Recommendation::create([
        'conversation_id' => $this->conversation->id,
        'turn' => 1,
        'urgency' => 'routine',
        'care_path' => 'specialist_visit',
        'summary' => 'x',
    ]);

    expect(fn () => $recommendation->doctors()->attach(99999, ['position' => 0]))->toThrow(QueryException::class, 'FOREIGN KEY')
        ->and(fn () => $recommendation->hospitals()->attach(Hospital::max('id') + 1, ['position' => 0]))->toThrow(QueryException::class, 'FOREIGN KEY');
});

it('will not put doctors on a card headed with a specialty they do not practise', function () {
    // What the small model did in the live evaluation: search Riyadh with no
    // specialty filter, then present the results as oncologists.
    $search = $this->tools->execute('search_doctors', doctorSearch(['city' => 'Riyadh']), $this->context);
    $this->context->ledger->record($search->doctorIds, $search->hospitalIds);

    expect($search->payload['warning'])->toContain('No specialty filter was applied')
        ->and($search->payload['filters_applied'])->toBe(['city' => 'Riyadh']);

    $outcome = $this->tools->execute('present_recommendation', recommendation([
        'specialty' => 'oncology',
        'doctor_ids' => $search->doctorIds,
    ]), $this->context);

    expect($outcome->isError)->toBeTrue()
        ->and($outcome->payload['error'])->toBe('specialty_mismatch')
        ->and(array_values($outcome->payload['actual_specialties']))->toEqualCanonicalizing(['cardiology', 'internal_medicine', 'orthopedics'])
        ->and(Recommendation::count())->toBe(0);
});
