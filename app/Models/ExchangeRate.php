<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExchangeRate extends Model
{
    /**
     * Ofertas padrão: comprar X de uma moeda pagando Y de outra.
     *
     * @var list<array{pay_currency: string, pay_amount: int, receive_currency: string, receive_amount: int}>
     */
    public const SHOP_OFFERS = [
        [
            'pay_currency' => GameCurrency::KEY_RELICS,
            'pay_amount' => 15,
            'receive_currency' => GameCurrency::KEY_AURAS,
            'receive_amount' => 100,
        ],
        [
            'pay_currency' => GameCurrency::KEY_SEALS,
            'pay_amount' => 5,
            'receive_currency' => GameCurrency::KEY_AURAS,
            'receive_amount' => 100,
        ],
        [
            'pay_currency' => GameCurrency::KEY_SEALS,
            'pay_amount' => 50,
            'receive_currency' => GameCurrency::KEY_RELICS,
            'receive_amount' => 500,
        ],
        [
            'pay_currency' => GameCurrency::KEY_AURAS,
            'pay_amount' => 400,
            'receive_currency' => GameCurrency::KEY_RELICS,
            'receive_amount' => 500,
        ],
        [
            'pay_currency' => GameCurrency::KEY_RELICS,
            'pay_amount' => 100,
            'receive_currency' => GameCurrency::KEY_SEALS,
            'receive_amount' => 50,
        ],
        [
            'pay_currency' => GameCurrency::KEY_AURAS,
            'pay_amount' => 300,
            'receive_currency' => GameCurrency::KEY_SEALS,
            'receive_amount' => 50,
        ],
    ];

    protected $fillable = [
        'pay_currency',
        'receive_currency',
        'pay_amount',
        'receive_amount',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'pay_amount' => 'integer',
            'receive_amount' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function exchanges(): HasMany
    {
        return $this->hasMany(CurrencyExchange::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Garante as 6 ofertas padrão (cada moeda comprável com as outras duas).
     */
    public static function ensureShopOffers(): void
    {
        foreach (self::SHOP_OFFERS as $offer) {
            self::query()->firstOrCreate(
                [
                    'pay_currency' => $offer['pay_currency'],
                    'receive_currency' => $offer['receive_currency'],
                ],
                [
                    'pay_amount' => $offer['pay_amount'],
                    'receive_amount' => $offer['receive_amount'],
                    'is_active' => true,
                ],
            );
        }
    }

    public function usesCurrency(string $key): bool
    {
        return $this->pay_currency === $key || $this->receive_currency === $key;
    }

    public function offerLabel(): string
    {
        return 'Comprar '
            .GameCurrency::format($this->receive_currency, $this->receive_amount)
            .' pagando '
            .GameCurrency::format($this->pay_currency, $this->pay_amount);
    }
}
