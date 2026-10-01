<?php

namespace App\Models;

use App\Enums\Specialty;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $hospital_id
 * @property Specialty $specialty
 * @property string $name_en
 * @property string $name_ar
 * @property string $focus_en
 * @property string $focus_ar
 * @property int $years_experience
 * @property list<string> $languages
 * @property int $consultation_fee_usd
 * @property bool $offers_second_opinion
 * @property bool $offers_telemedicine
 * @property string $rating
 * @property CarbonImmutable $next_available_on
 * @property-read Hospital $hospital
 */
class Doctor extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'specialty' => Specialty::class,
            'languages' => 'array',
            'offers_second_opinion' => 'boolean',
            'offers_telemedicine' => 'boolean',
            'next_available_on' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<Hospital, $this>
     */
    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
