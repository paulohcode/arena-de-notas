<?php

namespace App\Support;

use App\Models\GameCurrency;
use Illuminate\Validation\Rule;

class PetCatalog
{
    public const DEFAULT_STOCK = 2;

    public const DEFAULT_FORM_RELICS = 300;

    public const DEFAULT_FORM_SEALS = 40;

    public const DEFAULT_FORM_AURAS = 300;

    /**
     * @var array<string, string>
     */
    public const RARITIES = [
        'common' => 'Comum',
        'uncommon' => 'Incomum',
        'rare' => 'Raro',
        'epic' => 'Épico',
    ];

    /**
     * @var array<string, string>
     */
    public const AURA_COLORS = [
        'ember' => 'Brasa',
        'frost' => 'Gelo',
        'storm' => 'Relâmpago',
        'vigil' => 'Vigília',
        'aurora' => 'Aurora',
        'shadow' => 'Sombra',
        'bloom' => 'Flor',
    ];

    /**
     * Catálogo curado de mascotes. Preços altos (~10 dias de farm no comum).
     *
     * @var array<string, array{
     *     name: string,
     *     description: string,
     *     rarity: string,
     *     sprite_key: string,
     *     price_relics: int,
     *     price_seals: int,
     *     price_auras: int,
     *     combat_bonus: float,
     *     stock: int
     * }>
     */
    public const SPECIES = [
        'owl_sage' => [
            'name' => 'Coruja Sábia',
            'description' => 'Observa a arena e sussurra a próxima jogada.',
            'rarity' => 'common',
            'sprite_key' => 'owl',
            'price_relics' => 280,
            'price_seals' => 40,
            'price_auras' => 280,
            'combat_bonus' => 0.02,
            'stock' => self::DEFAULT_STOCK,
        ],
        'rune_wolf' => [
            'name' => 'Lobo Rúnico',
            'description' => 'Marcas antigas brilham no pelo quando a luta começa.',
            'rarity' => 'common',
            'sprite_key' => 'wolf',
            'price_relics' => 300,
            'price_seals' => 42,
            'price_auras' => 300,
            'combat_bonus' => 0.02,
            'stock' => self::DEFAULT_STOCK,
        ],
        'ember_fox' => [
            'name' => 'Raposa de Brasa',
            'description' => 'Cauda flamejante que aquece a coragem do dono.',
            'rarity' => 'uncommon',
            'sprite_key' => 'fox',
            'price_relics' => 450,
            'price_seals' => 65,
            'price_auras' => 450,
            'combat_bonus' => 0.03,
            'stock' => self::DEFAULT_STOCK,
        ],
        'storm_raven' => [
            'name' => 'Corvo da Tempestade',
            'description' => 'Asas de trovões e olhares que cortam a névoa.',
            'rarity' => 'uncommon',
            'sprite_key' => 'raven',
            'price_relics' => 500,
            'price_seals' => 70,
            'price_auras' => 500,
            'combat_bonus' => 0.035,
            'stock' => self::DEFAULT_STOCK,
        ],
        'aurora_stag' => [
            'name' => 'Cervo da Aurora',
            'description' => 'Chifres de aurora que iluminam o campo.',
            'rarity' => 'rare',
            'sprite_key' => 'stag',
            'price_relics' => 700,
            'price_seals' => 95,
            'price_auras' => 700,
            'combat_bonus' => 0.05,
            'stock' => self::DEFAULT_STOCK,
        ],
        'bronze_dragon' => [
            'name' => 'Dragão de Bronze',
            'description' => 'Pequeno dragão de escamas de bronze polido.',
            'rarity' => 'rare',
            'sprite_key' => 'dragon',
            'price_relics' => 750,
            'price_seals' => 100,
            'price_auras' => 750,
            'combat_bonus' => 0.05,
            'stock' => self::DEFAULT_STOCK,
        ],
        'vigil_phoenix' => [
            'name' => 'Fênix da Vigília',
            'description' => 'Renascida das cinzas de cada temporada.',
            'rarity' => 'epic',
            'sprite_key' => 'phoenix',
            'price_relics' => 1100,
            'price_seals' => 140,
            'price_auras' => 1100,
            'combat_bonus' => 0.07,
            'stock' => self::DEFAULT_STOCK,
        ],
        'void_serpent' => [
            'name' => 'Serpente do Vazio',
            'description' => 'Escala o vazio entre as notas e os turnos.',
            'rarity' => 'epic',
            'sprite_key' => 'serpent',
            'price_relics' => 1200,
            'price_seals' => 150,
            'price_auras' => 1200,
            'combat_bonus' => 0.075,
            'stock' => self::DEFAULT_STOCK,
        ],
    ];

    /**
     * @return array<string, list<string|object>>
     */
    public static function itemRules(bool $requireGif = false): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:255'],
            'rarity' => ['required', 'string', Rule::in(array_keys(self::RARITIES))],
            'sprite_key' => ['nullable', 'string', 'max:40'],
            'price_relics' => ['required', 'integer', 'min:0', 'max:9999'],
            'price_seals' => ['required', 'integer', 'min:0', 'max:9999'],
            'price_auras' => ['required', 'integer', 'min:0', 'max:9999'],
            'combat_bonus_percent' => ['required', 'numeric', 'min:0', 'max:15'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:99'],
            'active' => ['sometimes', 'boolean'],
            'gif' => [
                $requireGif ? 'required' : 'nullable',
                'file',
                'mimes:gif,webp,png',
                'max:2048',
            ],
        ];
    }

    /**
     * @return array<string, list<string|object>>
     */
    public static function itemUpdateRules(): array
    {
        $rules = self::itemRules();
        unset($rules['stock']);

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function itemMessages(): array
    {
        return [
            'name.required' => 'Informe o nome do mascote.',
            'name.max' => 'O nome pode ter no máximo 60 caracteres.',
            'rarity.required' => 'Escolha a raridade.',
            'rarity.in' => 'A raridade é inválida.',
            'price_relics.required' => 'Informe o preço em '.GameCurrency::label('relics').'.',
            'price_seals.required' => 'Informe o preço em '.GameCurrency::label('seals').'.',
            'price_auras.required' => 'Informe o preço em '.GameCurrency::label('auras').'.',
            'combat_bonus_percent.required' => 'Informe o bônus de poder.',
            'combat_bonus_percent.max' => 'O bônus do mascote não pode passar de 15%.',
            'gif.mimes' => 'Envie um GIF, WebP ou PNG.',
            'gif.max' => 'O arquivo do mascote pode ter no máximo 2 MB.',
        ];
    }

    /**
     * @return array<string, list<string|object>>
     */
    public static function purchaseRules(): array
    {
        return [
            'pet_id' => ['required', 'integer', 'exists:pets,id'],
            'custom_name' => ['required', 'string', 'min:2', 'max:20'],
            'aura_color' => ['required', 'string', Rule::in(array_keys(self::AURA_COLORS))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function purchaseMessages(): array
    {
        return [
            'custom_name.required' => 'Escolha um nome para o mascote.',
            'custom_name.min' => 'O nome do mascote precisa ter pelo menos 2 caracteres.',
            'custom_name.max' => 'O nome do mascote pode ter no máximo 20 caracteres.',
            'aura_color.required' => 'Escolha a cor da aura.',
            'aura_color.in' => 'A cor da aura é inválida.',
        ];
    }

    public static function percentToBonus(float|int|string|null $percent): float
    {
        return round(((float) $percent) / 100, 4);
    }

    public static function hasSpecies(string $key): bool
    {
        return array_key_exists($key, self::SPECIES);
    }

    /**
     * @return list<string>
     */
    public static function spriteKeys(): array
    {
        return collect(self::SPECIES)
            ->pluck('sprite_key')
            ->unique()
            ->values()
            ->all();
    }
}
