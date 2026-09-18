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
     * Na edição o par de moedas fica travado — só quantidades e status.
     *
     * @return array<string, list<string|object>>
     */
    public static function updateRules(): array
    {
        return [
            'receive_amount' => ['required', 'integer', 'min:1', 'max:99999'],
            'pay_amount' => ['required', 'integer', 'min:1', 'max:99999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, list<string|object>>
     */
    private static function baseRules(): array
    {
        return [
            'receive_currency' => ['required', 'string', Rule::in(GameCurrency::SHOP_KEYS)],
            'receive_amount' => ['required', 'integer', 'min:1', 'max:99999'],
            'pay_currency' => ['required', 'string', Rule::in(GameCurrency::SHOP_KEYS), 'different:receive_currency'],
            'pay_amount' => ['required', 'integer', 'min:1', 'max:99999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'receive_currency.required' => 'Escolha a moeda que o aluno compra.',
            'receive_currency.in' => 'A moeda comprada é inválida.',
            'receive_amount.required' => 'Informe quanto o aluno recebe.',
            'receive_amount.min' => 'A quantidade recebida deve ser pelo menos 1.',
            'pay_currency.required' => 'Escolha a moeda de pagamento.',
            'pay_currency.in' => 'A moeda de pagamento é inválida.',
            'pay_currency.different' => 'A moeda de pagamento precisa ser diferente da moeda comprada.',
            'pay_amount.required' => 'Informe quanto o aluno paga.',
            'pay_amount.min' => 'A quantidade paga deve ser pelo menos 1.',
        ];
    }

    /**
     * Uma oferta por combinação pagar→receber.
     * Ex.: não pode existir 100 Relíquias por 10 Selos e outra 120 Relíquias por 15 Selos.
     *
     * @return list<\Closure(Validator): void>
     */
    public static function uniqueOfferAfter(?ExchangeRate $except = null): array
    {
        return [
            function (Validator $validator) use ($except): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $payCurrency = (string) $validator->getData()['pay_currency'];
                $receiveCurrency = (string) $validator->getData()['receive_currency'];

                $query = ExchangeRate::query()
                    ->where('pay_currency', $payCurrency)
                    ->where('receive_currency', $receiveCurrency);

                if ($except) {
                    $query->whereKeyNot($except->id);
                }

                $existing = $query->first();

                if ($existing) {
                    $validator->errors()->add(
                        'pay_currency',
                        'Já existe câmbio de '
                        .GameCurrency::label($payCurrency)
                        .' → '
                        .GameCurrency::label($receiveCurrency)
                        .' ('
                        .$existing->offerLabel()
                        .'). Edite essa oferta em vez de cadastrar outra com valores diferentes.',
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
            'lots' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function tradeMessages(): array
    {
        return [
            'exchange_rate_id.required' => 'Escolha uma oferta.',
            'exchange_rate_id.exists' => 'A oferta escolhida não existe.',
            'lots.required' => 'Informe quantos lotes deseja comprar.',
            'lots.min' => 'Compre pelo menos 1 lote.',
            'lots.max' => 'Você pode comprar no máximo 999 lotes de uma vez.',
        ];
    }
}
