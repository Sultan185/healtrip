<?php

namespace App\Models;

use App\Enums\Specialty;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name_en
 * @property string $name_ar
 * @property string $country_code
 * @property string $city_en
 * @property string $city_ar
 * @property string $address_en
 * @property string $phone
 * @property bool $has_emergency
 * @property string|null $emergency_phone
 * @property string|null $accreditation
 * @property list<string> $languages
 * @property bool $international_patient_desk
 * @property-read Collection<int, HospitalSpecialty> $specialtyLinks
 */
class Hospital extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_emergency' => 'boolean',
            'international_patient_desk' => 'boolean',
            'languages' => 'array',
        ];
    }

    /**
     * @return HasMany<Doctor, $this>
     */
    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }

    /**
     * @return HasMany<HospitalSpecialty, $this>
     */
    public function specialtyLinks(): HasMany
    {
        return $this->hasMany(HospitalSpecialty::class)->orderBy('specialty');
    }

    /**
     * @return list<Specialty>
     */
    public function specialties(): array
    {
        return array_values($this->specialtyLinks->map(fn (HospitalSpecialty $link) => $link->specialty)->all());
    }

    /**
     * @param  list<Specialty>  $specialties
     */
    public function syncSpecialties(array $specialties): void
    {
        $this->specialtyLinks()->delete();

        $this->specialtyLinks()->createMany(array_map(
            fn (Specialty $specialty) => ['specialty' => $specialty],
            $specialties,
        ));

        $this->unsetRelation('specialtyLinks');
    }
}
