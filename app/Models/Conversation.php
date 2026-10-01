<?php

namespace App\Models;

use App\Enums\Urgency;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $owner_hash
 * @property string $locale
 * @property string $provider
 * @property Urgency|null $urgency
 * @property int $turns
 */
class Conversation extends Model
{
    use HasUlids;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'urgency' => Urgency::class,
            'turns' => 'integer',
        ];
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    /**
     * @return HasMany<ToolCall, $this>
     */
    public function toolCalls(): HasMany
    {
        return $this->hasMany(ToolCall::class)->orderBy('id');
    }

    /**
     * @return HasMany<Recommendation, $this>
     */
    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class)->orderBy('id');
    }

    /**
     * Raise the conversation's urgency. It is a high-water mark: once a
     * conversation has been flagged it is never downgraded.
     */
    public function escalateTo(Urgency $urgency): void
    {
        $raised = Urgency::max($urgency, $this->urgency);

        if ($raised !== $this->urgency) {
            $this->update(['urgency' => $raised]);
        }
    }
}
