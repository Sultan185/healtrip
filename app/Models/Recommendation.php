<?php

namespace App\Models;

use App\Enums\CarePath;
use App\Enums\Specialty;
use App\Enums\Urgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $conversation_id
 * @property int $turn
 * @property Urgency $urgency
 * @property CarePath $care_path
 * @property Specialty|null $specialty
 * @property string $summary
 */
class Recommendation extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'urgency' => Urgency::class,
            'care_path' => CarePath::class,
            'specialty' => Specialty::class,
            'turn' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Doctor, $this>
     */
    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class)->withPivot('position')->orderByPivot('position');
    }

    /**
     * @return BelongsToMany<Hospital, $this>
     */
    public function hospitals(): BelongsToMany
    {
        return $this->belongsToMany(Hospital::class)->withPivot('position')->orderByPivot('position');
    }
}
