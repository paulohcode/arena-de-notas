<?php

namespace App\Models;

use Database\Factories\GameEventAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameEventAttempt extends Model
{
    /** @use HasFactory<GameEventAttemptFactory> */
    use HasFactory;

    protected $fillable = [
        'game_event_id',
        'student_id',
        'class_id',
        'correct_count',
        'correct_time_ms',
        'current_question_position',
        'current_question_shown_at',
        'started_at',
        'finished_at',
        'rewards_granted',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'correct_count' => 0,
        'correct_time_ms' => 0,
        'rewards_granted' => false,
    ];

    protected function casts(): array
    {
        return [
            'correct_count' => 'integer',
            'correct_time_ms' => 'integer',
            'current_question_position' => 'integer',
            'current_question_shown_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'rewards_granted' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(GameEvent::class, 'game_event_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(GameEventAnswer::class);
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }
}
