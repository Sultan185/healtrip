<?php

namespace App\Models;

use App\Enums\Specialty;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $hospital_id
 * @property Specialty $specialty
 */
class HospitalSpecialty extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'hospital_specialty';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['specialty' => Specialty::class];
    }
}
