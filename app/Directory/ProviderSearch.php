<?php

namespace App\Directory;

use App\Enums\Specialty;
use App\Models\Doctor;
use App\Models\Hospital;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The one place the provider directory is queried. The REST endpoints and
 * the agent's tools both go through here, so a human browsing the API and
 * the model see the same rows in the same order.
 *
 * Filters are typed values bound as query parameters; nothing the model or
 * a visitor writes ever becomes SQL.
 */
class ProviderSearch
{
    /**
     * @return SearchResult<Doctor>
     */
    public function doctors(DoctorFilters $filters, int $limit): SearchResult
    {
        $query = Doctor::query()
            ->when($filters->specialty, fn (Builder $q, Specialty $specialty) => $q->where('specialty', $specialty->value))
            ->when($filters->language, fn (Builder $q, string $language) => $q->whereJsonContains('languages', $language))
            ->when($filters->secondOpinionOnly, fn (Builder $q) => $q->where('offers_second_opinion', true))
            ->when($filters->telemedicineOnly, fn (Builder $q) => $q->where('offers_telemedicine', true))
            ->when($filters->maxFeeUsd !== null, fn (Builder $q) => $q->where('consultation_fee_usd', '<=', $filters->maxFeeUsd));

        if ($filters->country !== null || $filters->city !== null) {
            $query->whereHas('hospital', fn (Builder $q) => $this->applyLocation($q, $filters->country, $filters->city));
        }

        $total = $query->count();

        $items = $query
            ->with('hospital')
            ->orderByDesc('rating')
            ->orderByDesc('years_experience')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return new SearchResult($items, $total);
    }

    /**
     * @return SearchResult<Hospital>
     */
    public function hospitals(HospitalFilters $filters, int $limit): SearchResult
    {
        $query = Hospital::query()
            ->when($filters->emergencyOnly, fn (Builder $q) => $q->where('has_emergency', true))
            ->when($filters->specialty, fn (Builder $q, Specialty $specialty) => $q->whereHas(
                'specialtyLinks',
                fn (Builder $link) => $link->where('specialty', $specialty->value),
            ));

        $this->applyLocation($query, $filters->country, $filters->city);

        $total = $query->count();

        $items = $query
            ->with('specialtyLinks')
            ->orderByDesc('international_patient_desk')
            ->orderBy('name_en')
            ->limit($limit)
            ->get();

        return new SearchResult($items, $total);
    }

    /**
     * Where the directory actually has doctors. Returned alongside an empty
     * search so the agent can offer a real alternative instead of guessing.
     *
     * @return list<array{specialty: string, country: string, city: string, doctors: int}>
     */
    public function doctorCoverage(?Specialty $specialty = null): array
    {
        return array_values(DB::table('doctors')
            ->join('hospitals', 'hospitals.id', '=', 'doctors.hospital_id')
            ->when($specialty, fn ($q, Specialty $specialty) => $q->where('doctors.specialty', $specialty->value))
            ->groupBy('doctors.specialty', 'hospitals.country_code', 'hospitals.city_en')
            ->orderBy('doctors.specialty')
            ->orderBy('hospitals.country_code')
            ->orderBy('hospitals.city_en')
            ->get(['doctors.specialty', 'hospitals.country_code', 'hospitals.city_en', DB::raw('count(*) as doctors')])
            ->map(fn (object $row) => [
                'specialty' => (string) $row->specialty,
                'country' => (string) $row->country_code,
                'city' => (string) $row->city_en,
                'doctors' => (int) $row->doctors,
            ])
            ->all());
    }

    /**
     * @param  Builder<Hospital>  $query
     */
    private function applyLocation(Builder $query, ?string $country, ?string $city): void
    {
        if ($country !== null) {
            // An unrecognised country must match nothing rather than be
            // ignored: silently dropping the filter would return providers
            // from the wrong country.
            $query->where('country_code', Countries::resolve($country) ?? '');
        }

        if ($city !== null) {
            $city = trim($city);

            // City names are stored in plain ASCII, so fold what was typed
            // the same way: "İstanbul" and "istanbul" are the same place.
            $query->where(fn (Builder $q) => $q
                ->whereRaw('lower(city_en) = ?', [mb_strtolower(Str::ascii($city))])
                ->orWhere('city_ar', $city));
        }
    }
}
