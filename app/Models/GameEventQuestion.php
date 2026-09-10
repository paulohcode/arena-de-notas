<?php

namespace App\Models;

use Database\Factories\GameEventQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameEventQuestion extends Model
{
    /** @use HasFactory<GameEventQuestionFactory> */
    use HasFactory;

    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';

    public const TYPE_TRUE_FALSE = 'true_false';

    protected $fillable = [
        'game_event_id',
        'prompt',
        'type',
        'options',
        'correct_index',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'correct_index' => 'integer',
            'position' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(GameEvent::class, 'game_event_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(GameEventAnswer::class);
    }

    /**
     * Public payload without revealing the correct answer.
     *
     * @return array{id: int, position: int, prompt: string, type: string, options: list<string>}
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'prompt' => $this->prompt,
            'type' => $this->type,
            'options' => array_values($this->options ?? []),
        ];
    }
}
