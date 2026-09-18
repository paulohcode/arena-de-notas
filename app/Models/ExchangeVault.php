<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExchangeVault extends Model
{
    protected $fillable = [
        'area_id',
        'relics',
        'seals',
        'auras',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'relics' => 0,
        'seals' => 0,
        'auras' => 0,
    ];

    protected function casts(): array
    {
        return [
            'relics' => 'integer',
            'seals' => 'integer',
            'auras' => 'integer',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function trades(): HasMany
    {
        return $this->hasMany(CurrencyTrade::class, 'vault_id');
    }

    public function totalBalance(): int
    {
        return (int) $this->relics + (int) $this->seals + (int) $this->auras;
    }

    public function isEmpty(): bool
    {
        return $this->totalBalance() === 0;
    }

    public function deposit(string $currency, int $amount): void
    {
        $this->{$currency} = (int) $this->{$currency} + $amount;
        $this->save();
    }

    /**
     * @return array{relics: int, seals: int, auras: int}
     */
    public function takeAll(): array
    {
        $snapshot = [
            'relics' => (int) $this->relics,
            'seals' => (int) $this->seals,
            'auras' => (int) $this->auras,
        ];

        $this->relics = 0;
        $this->seals = 0;
        $this->auras = 0;
        $this->save();

        return $snapshot;
    }

    public static function forArea(Area $area): self
    {
        return static::query()->firstOrCreate(
            ['area_id' => $area->id],
            ['relics' => 0, 'seals' => 0, 'auras' => 0],
        );
    }
}
