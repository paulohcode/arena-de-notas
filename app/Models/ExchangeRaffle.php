<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRaffle extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'area_id',
        'admin_id',
        'winner_id',
        'relics',
        'seals',
        'auras',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'relics' => 'integer',
            'seals' => 'integer',
            'auras' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }
}
