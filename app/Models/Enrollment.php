<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'class_id',
    'student_id',
    'ranking_visible',
    'xp',
    'glory',
    'arena_wins',
    'arena_losses',
    'behavior_score',
])]
class Enrollment extends Model
{
    protected function casts(): array
    {
        return [
            'ranking_visible' => 'boolean',
            'xp' => 'integer',
            'glory' => 'integer',
            'arena_wins' => 'integer',
            'arena_losses' => 'integer',
            'behavior_score' => 'float',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
