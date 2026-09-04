<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['class_id', 'name', 'color', 'emblem'])]
class Team extends Model
{
    public const EMBLEMS = [
        'shield' => '🛡️',
        'sword' => '⚔️',
        'dragon' => '🐉',
        'crown' => '👑',
        'fire' => '🔥',
        'wolf' => '🐺',
        'owl' => '🦉',
        'lightning' => '⚡',
    ];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'student_id')->withTimestamps();
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function emblemIcon(): string
    {
        return self::EMBLEMS[$this->emblem] ?? '🛡️';
    }
}
