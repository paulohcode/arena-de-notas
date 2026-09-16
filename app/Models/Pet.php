<?php

namespace App\Models;

use App\Support\PetCatalog;
use Database\Factories\PetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Pet extends Model
{
    /** @use HasFactory<PetFactory> */
    use HasFactory;

    protected $fillable = [
        'class_id',
        'species_key',
        'name',
        'description',
        'rarity',
        'sprite_key',
        'gif_path',
        'price_relics',
        'price_seals',
        'price_auras',
        'combat_bonus',
        'stock',
        'active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'active' => true,
        'stock' => 0,
        'price_relics' => 0,
        'price_seals' => 0,
        'price_auras' => 0,
        'combat_bonus' => 0.02,
    ];

    protected function casts(): array
    {
        return [
            'class_id' => 'integer',
            'price_relics' => 'integer',
            'price_seals' => 'integer',
            'price_auras' => 'integer',
            'combat_bonus' => 'float',
            'stock' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function ownerships(): HasMany
    {
        return $this->hasMany(EnrollmentPet::class);
    }

    public function combatBonusPercent(): float
    {
        return round(((float) $this->combat_bonus) * 100, 1);
    }

    public function gifUrl(): ?string
    {
        if (! filled($this->gif_path)) {
            return null;
        }

        if (! Storage::disk('public')->exists($this->gif_path)) {
            return null;
        }

        // Relative path so the image loads on the same host as the page
        // (avoids APP_URL=localhost leaking into production).
        return '/storage/'.ltrim($this->gif_path, '/');
    }

    public function spriteKey(): string
    {
        return $this->sprite_key
            ?? $this->species_key
            ?? 'owl';
    }

    public function rarityLabel(): string
    {
        return PetCatalog::RARITIES[$this->rarity] ?? $this->rarity;
    }

    public function priceLine(): string
    {
        $parts = [];

        if ((int) $this->price_relics > 0) {
            $parts[] = GameCurrency::format('relics', (int) $this->price_relics);
        }
        if ((int) $this->price_seals > 0) {
            $parts[] = GameCurrency::format('seals', (int) $this->price_seals);
        }
        if ((int) $this->price_auras > 0) {
            $parts[] = GameCurrency::format('auras', (int) $this->price_auras);
        }

        return $parts === [] ? 'Grátis' : implode(' · ', $parts);
    }

    public function belongsToClass(SchoolClass $class): bool
    {
        return (int) $this->class_id === (int) $class->id;
    }

    /**
     * @return array{id: int, name: string, description: ?string, rarity: string, rarity_label: string, sprite_key: string, gif_url: ?string, price_relics: int, price_seals: int, price_auras: int, combat_bonus: float, combat_bonus_percent: float, stock: int, active: bool, species_key: ?string}
     */
    public function toShopArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'rarity' => $this->rarity,
            'rarity_label' => $this->rarityLabel(),
            'sprite_key' => $this->spriteKey(),
            'gif_url' => $this->gifUrl(),
            'price_relics' => (int) $this->price_relics,
            'price_seals' => (int) $this->price_seals,
            'price_auras' => (int) $this->price_auras,
            'combat_bonus' => (float) $this->combat_bonus,
            'combat_bonus_percent' => $this->combatBonusPercent(),
            'stock' => (int) $this->stock,
            'active' => (bool) $this->active,
            'species_key' => $this->species_key,
        ];
    }
}
