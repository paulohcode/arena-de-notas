<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Activity extends Model
{
    public const TYPE_INDIVIDUAL = 'individual';

    public const TYPE_TEAM = 'team';

    public const TYPE_EVENT = 'event';

    protected $fillable = ['class_id', 'name', 'type', 'max_score', 'weight'];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function gameEvent(): HasOne
    {
        return $this->hasOne(GameEvent::class);
    }

    public function isTeam(): bool
    {
        return $this->type === self::TYPE_TEAM;
    }

    public function isEvent(): bool
    {
        return $this->type === self::TYPE_EVENT;
    }
}
