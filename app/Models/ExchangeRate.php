<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExchangeRate extends Model
{
    public const DIRECTION_A_TO_B = 'a_to_b';

    public const DIRECTION_B_TO_A = 'b_to_a';

    protected $fillable = [
        'currency_a',
        'currency_b',
        'amount_a',
        'amount_b',
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
            'amount_a' => 'integer',
            'amount_b' => 'integer',
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
     * @return array{currency_a: string, currency_b: string, amount_a: int, amount_b: int}
     */
    public static function normalizePair(string $currencyOne, string $currencyTwo, int $amountOne, int $amountTwo): array
    {
        if ($currencyOne === $currencyTwo) {
            return [
                'currency_a' => $currencyOne,
                'currency_b' => $currencyTwo,
                'amount_a' => $amountOne,
                'amount_b' => $amountTwo,
            ];
        }

        if ($currencyOne < $currencyTwo) {
            return [
                'currency_a' => $currencyOne,
                'currency_b' => $currencyTwo,
                'amount_a' => $amountOne,
                'amount_b' => $amountTwo,
            ];
        }

        return [
            'currency_a' => $currencyTwo,
            'currency_b' => $currencyOne,
            'amount_a' => $amountTwo,
            'amount_b' => $amountOne,
        ];
    }

    public function usesCurrency(string $key): bool
    {
        return $this->currency_a === $key || $this->currency_b === $key;
    }

    /**
     * @return array{pay_currency: string, receive_currency: string, pay_amount: int, receive_amount: int}|null
     */
    public function resolveDirection(string $direction): ?array
    {
        return match ($direction) {
            self::DIRECTION_A_TO_B => [
                'pay_currency' => $this->currency_a,
                'receive_currency' => $this->currency_b,
                'pay_amount' => (int) $this->amount_a,
                'receive_amount' => (int) $this->amount_b,
            ],
            self::DIRECTION_B_TO_A => [
                'pay_currency' => $this->currency_b,
                'receive_currency' => $this->currency_a,
                'pay_amount' => (int) $this->amount_b,
                'receive_amount' => (int) $this->amount_a,
            ],
            default => null,
        };
    }

    public function parityLabel(): string
    {
        return GameCurrency::format($this->currency_a, $this->amount_a)
            .' = '
            .GameCurrency::format($this->currency_b, $this->amount_b);
    }
}
