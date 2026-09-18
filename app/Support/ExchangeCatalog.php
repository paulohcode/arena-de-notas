<?php

namespace App\Support;

use App\Models\ExchangeRate;
use App\Models\GameCurrency;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ExchangeCatalog
{
    /**
     * @return array<string, list<string|object>>
     */
    public static function storeRules(): array
    {
        return self::baseRules();
    }

    /**
     * @return array<string, list<string|object>>
     */
    public static function updateRules(): array
    {
        return [
            ...self::baseRules(),
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, list<string|object>>
     */
    private static function baseRules(): array
    {
        return [
            'currency_a' => ['required', 'string', Rule::in(GameCurrency::SHOP_KEYS)],
            'currency_b' => ['required', 'string', Rule::in(GameCurrency::SHOP_KEYS), 'different:currency_a'],
            'amount_a' => ['required', 'integer', 'min:1', 'max:99999'],
            'amount_b' => ['required', 'integer', 'min:1', 'max:99999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'currency_a.required' => 'Escolha a primeira moeda do par.',
            'currency_a.in' => 'A primeira moeda do par é inválida.',
            'currency_b.required' => 'Escolha a segunda moeda do par.',
            'currency_b.in' => 'A segunda moeda do par é inválida.',
            'currency_b.different' => 'As moedas do par precisam ser diferentes.',
            'amount_a.required' => 'Informe a quantidade da primeira moeda.',
            'amount_a.min' => 'A quantidade da primeira moeda deve ser pelo menos 1.',
            'amount_b.required' => 'Informe a quantidade da segunda moeda.',
            'amount_b.min' => 'A quantidade da segunda moeda deve ser pelo menos 1.',
        ];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public static function uniquePairAfter(?ExchangeRate $except = null): array
    {
        return [
            function (Validator $validator) use ($except): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $currencyA = (string) $validator->getData()['currency_a'];
                $currencyB = (string) $validator->getData()['currency_b'];
                $normalized = ExchangeRate::normalizePair($currencyA, $currencyB, 1, 1);

                $query = ExchangeRate::query()
                    ->where('currency_a', $normalized['currency_a'])
                    ->where('currency_b', $normalized['currency_b']);

                if ($except) {
                    $query->whereKeyNot($except->id);
                }

                if ($query->exists()) {
                    $validator->errors()->add(
                        'currency_b',
                        'Já existe uma cotação para este par de moedas.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, list<string|object>>
     */
    public static function tradeRules(): array
    {
        return [
            'exchange_rate_id' => ['required', 'integer', 'exists:exchange_rates,id'],
            'direction' => ['required', 'string', Rule::in([
                ExchangeRate::DIRECTION_A_TO_B,
                ExchangeRate::DIRECTION_B_TO_A,
            ])],
            'lots' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function tradeMessages(): array
    {
        return [
            'exchange_rate_id.required' => 'Escolha uma cotação.',
            'exchange_rate_id.exists' => 'A cotação escolhida não existe.',
            'direction.required' => 'Escolha o sentido da troca.',
            'direction.in' => 'O sentido da troca é inválido.',
            'lots.required' => 'Informe quantos lotes deseja trocar.',
            'lots.min' => 'Troque pelo menos 1 lote.',
            'lots.max' => 'Você pode trocar no máximo 999 lotes de uma vez.',
        ];
    }
}
