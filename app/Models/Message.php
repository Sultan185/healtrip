<?php

namespace App\Models;

use App\Enums\MessageKind;
use Illuminate\Database\Eloquent\Model;

/**
 * One entry of the transcript. Append-only: rows are inserted, never updated,
 * so the history replayed to the model is identical on every request.
 *
 * @property int $id
 * @property string $conversation_id
 * @property int $turn
 * @property string $role
 * @property MessageKind $kind
 * @property string|list<array<string, mixed>> $content
 * @property string|null $visible_text
 * @property array<string, mixed>|null $meta
 */
class Message extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => MessageKind::class,
            'content' => 'json',
            'meta' => 'array',
            'turn' => 'integer',
        ];
    }

    /**
     * The message in the shape the model API expects.
     *
     * @return array{role: string, content: string|list<array<string, mixed>>}
     */
    public function toApi(): array
    {
        return ['role' => $this->role, 'content' => $this->content];
    }
}
