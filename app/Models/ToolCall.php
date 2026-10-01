<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit record of one tool invocation: what the model asked for, what we
 * returned, and which provider IDs that put into play.
 *
 * @property int $id
 * @property string $conversation_id
 * @property int $turn
 * @property string $tool_use_id
 * @property string $name
 * @property array<string, mixed> $input
 * @property array<string, mixed> $output
 * @property bool $is_error
 * @property list<int>|null $doctor_ids
 * @property list<int>|null $hospital_ids
 * @property int $duration_ms
 */
class ToolCall extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
            'is_error' => 'boolean',
            'doctor_ids' => 'array',
            'hospital_ids' => 'array',
            'turn' => 'integer',
            'duration_ms' => 'integer',
        ];
    }
}
