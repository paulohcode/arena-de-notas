<?php

namespace App\Models;

use Database\Factories\GameEventAnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameEventAnswer extends Model
{
    /** @use HasFactory<GameEventAnswerFactory> */
    use HasFactory;

    protected $fillable = [
        'game_event_attempt_id',
        'game_event_question_id',
        'selected_index',
        'is_correct',
        'elapsed_ms',
    ];

    protected function casts(): array
    {
        return [
            'selected_index' => 'integer',
            'is_correct' => 'boolean',
            'elapsed_ms' => 'integer',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(GameEventAttempt::class, 'game_event_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(GameEventQuestion::class, 'game_event_question_id');
    }
}
