<?php

use App\Directory\DoctorFilters;
use App\Directory\HospitalFilters;
use App\Directory\ProviderSearch;
use App\Enums\Specialty;
use App\Models\Doctor;

beforeEach(fn () => $this->search = app(ProviderSearch::class));

it('filters doctors by specialty and returns the best rated first', function () {
    $result = $this->search->doctors(new DoctorFilters(specialty: Specialty::Cardiology), 5);

    expect($result->total)->toBe(Doctor::where('specialty', 'cardiology')->count())
        ->and($result->items)->toHaveCount(5)
        ->and($result->items->every(fn (Doctor $doctor) => $doctor->specialty === Specialty::Cardiology))->toBeTrue();

    $ratings = $result->items->map(fn (Doctor $doctor) => (float) $doctor->rating)->all();

    expect($ratings)->toBe(collect($ratings)->sortDesc()->values()->all());
});

it('understands a country however it is written', function (string $country) {
    $result = $this->search->doctors(new DoctorFilters(specialty: Specialty::Cardiology, country: $country), 10);

    expect($result->total)->toBeGreaterThan(0)
        ->and($result->items->every(fn (Doctor $doctor) => $doctor->hospital->country_code === 'TR'))->toBeTrue();
})->with(['TR', 'Turkey', 'türkiye', 'تركيا']);

it('returns nothing for a country the directory does not cover, instead of ignoring the filter', function () {
    $result = $this->search->doctors(new DoctorFilters(specialty: Specialty::Cardiology, country: 'Japan'), 10);

    expect($result->total)->toBe(0)->and($result->items)->toBeEmpty();
});

it('matches a city in English or Arabic', function (string $city) {
    $result = $this->search->doctors(new DoctorFilters(city: $city), 20);

    expect($result->total)->toBeGreaterThan(0)
        ->and($result->items->every(fn (Doctor $doctor) => $doctor->hospital->city_en === 'Cairo'))->toBeTrue();
})->with(['Cairo', 'cairo', 'القاهرة']);

it('matches a city written with local letters', function () {
    $result = $this->search->doctors(new DoctorFilters(specialty: Specialty::Cardiology, city: 'İstanbul'), 20);

    expect($result->total)->toBe(2);
});

it('combines language, second opinion and fee filters', function () {
    $result = $this->search->doctors(new DoctorFilters(
        specialty: Specialty::Cardiology,
        language: 'ar',
        secondOpinionOnly: true,
        maxFeeUsd: 100,
    ), 10);

    expect($result->total)->toBeGreaterThan(0);

    $result->items->each(function (Doctor $doctor) {
        expect($doctor->languages)->toContain('ar')
            ->and($doctor->offers_second_opinion)->toBeTrue()
            ->and($doctor->consultation_fee_usd)->toBeLessThanOrEqual(100);
    });
});

it('finds hospitals with an emergency department in a city', function () {
    $result = $this->search->hospitals(new HospitalFilters(city: 'Dubai', emergencyOnly: true), 5);

    expect($result->items)->toHaveCount(1)
        ->and($result->items->first()->name_en)->toBe('Gulf Crescent Specialty Hospital');
});

it('reports where a specialty is actually available', function () {
    $coverage = $this->search->doctorCoverage(Specialty::CardiacSurgery);

    expect(array_column($coverage, 'city'))->toEqualCanonicalizing(['Istanbul', 'Cairo', 'Frankfurt']);
});
