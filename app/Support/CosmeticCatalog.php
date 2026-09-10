<?php

namespace App\Support;

use App\Models\SchoolClass;
use App\Models\ShopItem;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CosmeticCatalog
{
    public const SLOT_FRAME = 'frame';

    public const SLOT_ACCESSORY = 'accessory';

    public const SLOT_TITLE = 'title';

    public const SLOT_AURA = 'aura';

    public const CURRENCY_RELICS = 'relics';

    public const CURRENCY_SEALS = 'seals';

    public const CURRENCY_AURAS = 'auras';

    /**
     * @var array<string, string>
     */
    public const CURRENCIES = [
        self::CURRENCY_RELICS => 'Relíquias',
        self::CURRENCY_SEALS => 'Selos',
        self::CURRENCY_AURAS => 'Aura',
    ];

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
    public const CSS_TONES = [
        'bronze' => 'Bronze',
        'silver' => 'Prata',
        'gold' => 'Ouro',
        'rune' => 'Rúnico',
        'aurora' => 'Aurora',
        'ember' => 'Brasa',
        'frost' => 'Gelo',
        'storm' => 'Relâmpago',
        'vigil' => 'Vigília',
    ];

    /**
     * @var array<string, array{slot: string, name: string, price: int, rarity: string, icon: string, currency?: string, css?: ?string, label?: ?string, class_id?: ?int, area_id?: ?int}>|null
     */
    private static ?array $customItems = null;

    /**
     * @var array<string, string>
     */
    public const SLOTS = [
        self::SLOT_FRAME => 'Molduras',
        self::SLOT_ACCESSORY => 'Acessórios',
        self::SLOT_TITLE => 'Títulos',
        self::SLOT_AURA => 'Auras',
    ];

    /**
     * Catálogo curado de cosméticos. Equipados também dão bônus de poder no duelo.
     *
     * @var array<string, array{slot: string, name: string, price: int, rarity: string, icon: string, currency?: string, css?: string, label?: string}>
     */
    public const ITEMS = [
        'frame_bronze' => [
            'slot' => self::SLOT_FRAME,
            'name' => 'Anel de Bronze',
            'price' => 25,
            'rarity' => 'common',
            'icon' => '🟤',
            'css' => 'bronze',
        ],
        'frame_silver' => [
            'slot' => self::SLOT_FRAME,
            'name' => 'Anel de Prata',
            'price' => 55,
            'rarity' => 'uncommon',
            'icon' => '⚪',
            'css' => 'silver',
        ],
        'frame_gold' => [
            'slot' => self::SLOT_FRAME,
            'name' => 'Anel de Ouro',
            'price' => 90,
            'rarity' => 'rare',
            'icon' => '🟡',
            'css' => 'gold',
        ],
        'frame_rune' => [
            'slot' => self::SLOT_FRAME,
            'name' => 'Anel Rúnico',
            'price' => 140,
            'rarity' => 'epic',
            'icon' => '🔮',
            'css' => 'rune',
        ],
        'frame_aurora' => [
            'slot' => self::SLOT_FRAME,
            'name' => 'Anel da Aurora',
            'price' => 12,
            'rarity' => 'rare',
            'currency' => self::CURRENCY_SEALS,
            'icon' => '🌅',
            'css' => 'aurora',
        ],
        'acc_crown' => [
            'slot' => self::SLOT_ACCESSORY,
            'name' => 'Coroa Menor',
            'price' => 40,
            'rarity' => 'common',
            'icon' => '👑',
        ],
        'acc_cape' => [
            'slot' => self::SLOT_ACCESSORY,
            'name' => 'Capa de Duelo',
            'price' => 50,
            'rarity' => 'uncommon',
            'icon' => '🧣',
        ],
        'acc_wings' => [
            'slot' => self::SLOT_ACCESSORY,
            'name' => 'Asas de Arena',
            'price' => 80,
            'rarity' => 'rare',
            'icon' => '🪽',
        ],
        'acc_star' => [
            'slot' => self::SLOT_ACCESSORY,
            'name' => 'Estrela Guardiã',
            'price' => 35,
            'rarity' => 'common',
            'icon' => '⭐',
        ],
        'acc_crystal' => [
            'slot' => self::SLOT_ACCESSORY,
            'name' => 'Cristal Arcano',
            'price' => 70,
            'rarity' => 'uncommon',
            'icon' => '💠',
        ],
        'acc_seal' => [
            'slot' => self::SLOT_ACCESSORY,
            'name' => 'Selo do Guardião',
            'price' => 8,
            'rarity' => 'uncommon',
            'currency' => self::CURRENCY_SEALS,
            'icon' => '🔏',
        ],
        'title_duelist' => [
            'slot' => self::SLOT_TITLE,
            'name' => 'Duelista',
            'price' => 30,
            'rarity' => 'common',
            'icon' => '⚔️',
            'label' => 'Duelista',
        ],
        'title_champion' => [
            'slot' => self::SLOT_TITLE,
            'name' => 'Campeão',
            'price' => 75,
            'rarity' => 'rare',
            'icon' => '🏆',
            'label' => 'Campeão',
        ],
        'title_legend' => [
            'slot' => self::SLOT_TITLE,
            'name' => 'Lenda',
            'price' => 150,
            'rarity' => 'epic',
            'icon' => '📜',
            'label' => 'Lenda',
        ],
        'title_assiduous' => [
            'slot' => self::SLOT_TITLE,
            'name' => 'Assíduo',
            'price' => 10,
            'rarity' => 'rare',
            'currency' => self::CURRENCY_SEALS,
            'icon' => '📅',
            'label' => 'Assíduo',
        ],
        'aura_ember' => [
            'slot' => self::SLOT_AURA,
            'name' => 'Aura de Brasa',
            'price' => 60,
            'rarity' => 'uncommon',
            'icon' => '🔥',
            'css' => 'ember',
        ],
        'aura_frost' => [
            'slot' => self::SLOT_AURA,
            'name' => 'Aura de Gelo',
            'price' => 60,
            'rarity' => 'uncommon',
            'icon' => '❄️',
            'css' => 'frost',
        ],
        'aura_storm' => [
            'slot' => self::SLOT_AURA,
            'name' => 'Aura de Relâmpago',
            'price' => 120,
            'rarity' => 'epic',
            'icon' => '⚡',
            'css' => 'storm',
        ],
        'aura_vigil' => [
            'slot' => self::SLOT_AURA,
            'name' => 'Aura da Vigília',
            'price' => 15,
            'rarity' => 'epic',
            'currency' => self::CURRENCY_SEALS,
            'icon' => '🕯️',
            'css' => 'vigil',
        ],
    ];

    public static function flush(): void
    {
        self::$customItems = null;
    }

    /**
     * @return array<string, array{id?: int, slot: string, name: string, price: int, rarity: string, icon: string, currency?: string, css?: ?string, label?: ?string, class_id?: ?int, area_id?: ?int, combat_bonus?: ?float}>
     */
    public static function customItems(): array
    {
        if (self::$customItems === null) {
            self::$customItems = ShopItem::query()
                ->where('prize_only', false)
                ->orderBy('id')
                ->get()
                ->mapWithKeys(fn (ShopItem $item): array => [$item->item_key => $item->toCatalogArray()])
                ->all();
        }

        return self::$customItems;
    }

    /**
     * @return array<string, array{id?: int, slot: string, name: string, price: int, rarity: string, icon: string, currency?: string, css?: ?string, label?: ?string, class_id?: ?int, area_id?: ?int, combat_bonus?: ?float}>
     */
    public static function itemsForClass(SchoolClass $class): array
    {
        $items = self::ITEMS;

        foreach (self::customItems() as $key => $item) {
            if (self::customItemIsAvailableTo($item, $class)) {
                $items[$key] = $item;
            }
        }

        return $items;
    }

    /**
     * @param  array{currency?: string, class_id?: ?int, area_id?: ?int, prize_only?: bool}  $item
     */
    public static function customItemIsAvailableTo(array $item, SchoolClass $class): bool
    {
        if (! empty($item['prize_only'])) {
            return false;
        }

        $currency = $item['currency'] ?? self::CURRENCY_RELICS;

        if ($currency === self::CURRENCY_AURAS) {
            $areaId = $item['area_id'] ?? null;

            return $areaId === null || ((int) $class->area_id === (int) $areaId);
        }

        $ownerId = $item['class_id'] ?? null;

        return $ownerId === null || (int) $ownerId === (int) $class->id;
    }

    /**
     * @return list<string>
     */
    public static function keysForClass(SchoolClass $class): array
    {
        return array_keys(self::itemsForClass($class));
    }

    public static function isAvailableTo(string $key, SchoolClass $class): bool
    {
        return array_key_exists($key, self::itemsForClass($class));
    }

    public static function uniqueKey(string $slot, string $name): string
    {
        $slug = str_replace('-', '_', Str::slug($name));
        if ($slug === '') {
            $slug = 'item';
        }

        $base = 'custom_'.$slot.'_'.$slug;
        $key = $base;
        $suffix = 2;

        while (self::has($key)) {
            $key = $base.'_'.$suffix;
            $suffix++;
        }

        return $key;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function itemRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'slot' => ['required', 'string', Rule::in(array_keys(self::SLOTS))],
            'price' => ['required', 'integer', 'min:1', 'max:9999'],
            'currency' => ['required', 'string', Rule::in(array_keys(self::CURRENCIES))],
            'rarity' => ['required', 'string', Rule::in(array_keys(self::RARITIES))],
            'icon' => ['required', 'string', 'max:32'],
            'css' => ['nullable', 'string', Rule::in(array_keys(self::CSS_TONES))],
            'label' => ['nullable', 'string', 'max:60'],
            'combat_bonus_percent' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:99'],
        ];
    }

    /**
     * @return array<string, list<string>>
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
            'name.required' => 'Informe o nome do item.',
            'name.max' => 'O nome do item pode ter no máximo 60 caracteres.',
            'slot.required' => 'Escolha o tipo do item.',
            'slot.in' => 'O tipo do item é inválido.',
            'price.required' => 'Informe o preço do item.',
            'price.integer' => 'O preço precisa ser um número inteiro.',
            'price.min' => 'O preço mínimo é 1.',
            'price.max' => 'O preço máximo é 9999.',
            'currency.required' => 'Escolha a moeda do item.',
            'currency.in' => 'A moeda do item é inválida.',
            'rarity.required' => 'Escolha a raridade do item.',
            'rarity.in' => 'A raridade do item é inválida.',
            'icon.required' => 'Informe um ícone para o item.',
            'icon.max' => 'O ícone pode ter no máximo 32 caracteres.',
            'css.in' => 'O visual do item é inválido.',
            'label.max' => 'O título exibido pode ter no máximo 60 caracteres.',
            'combat_bonus_percent.numeric' => 'O poder do item precisa ser um número.',
            'combat_bonus_percent.min' => 'O poder do item não pode ser negativo.',
            'combat_bonus_percent.max' => 'O poder do item não pode passar de 10%.',
            'stock.integer' => 'O estoque inicial precisa ser um número inteiro.',
            'stock.min' => 'O estoque inicial não pode ser negativo.',
            'stock.max' => 'O estoque inicial não pode passar de 99.',
        ];
    }

    /**
     * @return array{id?: int, slot: string, name: string, price: int, rarity: string, icon: string, currency?: string, css?: ?string, label?: ?string, class_id?: ?int, area_id?: ?int, combat_bonus?: ?float}|null
     */
    public static function item(string $key): ?array
    {
        return self::ITEMS[$key] ?? (self::customItems()[$key] ?? null);
    }

    public static function icon(string $key): string
    {
        return self::item($key)['icon'] ?? '✦';
    }

    public static function has(string $key): bool
    {
        return self::item($key) !== null;
    }

    public static function currency(string $key): string
    {
        $item = self::item($key);

        return $item['currency'] ?? self::CURRENCY_RELICS;
    }

    public static function usesSeals(string $key): bool
    {
        return self::currency($key) === self::CURRENCY_SEALS;
    }

    public static function usesAuras(string $key): bool
    {
        return self::currency($key) === self::CURRENCY_AURAS;
    }

    public static function isNonTradable(string $key): bool
    {
        return self::usesSeals($key) || self::usesAuras($key);
    }

    public static function currencyLabel(string $currency): string
    {
        return self::CURRENCIES[$currency] ?? 'Relíquias';
    }

    public static function slotColumn(string $slot): ?string
    {
        return match ($slot) {
            self::SLOT_FRAME => 'equipped_frame',
            self::SLOT_ACCESSORY => 'equipped_accessory',
            self::SLOT_TITLE => 'equipped_title',
            self::SLOT_AURA => 'equipped_aura',
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function keysForSlot(string $slot, ?SchoolClass $class = null): array
    {
        $source = $class ? self::itemsForClass($class) : array_merge(self::ITEMS, self::customItems());
        $keys = [];

        foreach ($source as $key => $item) {
            if ($item['slot'] === $slot) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @return array{frame: ?string, accessory: ?string, title: ?string, aura: ?string}
     */
    public static function loadoutFromEnrollment(object $source): array
    {
        return [
            'frame' => $source->equipped_frame ?? null,
            'accessory' => $source->equipped_accessory ?? null,
            'title' => $source->equipped_title ?? null,
            'aura' => $source->equipped_aura ?? null,
        ];
    }

    public static function titleLabel(?string $itemKey): ?string
    {
        if (! filled($itemKey)) {
            return null;
        }

        $item = self::item($itemKey);

        return $item['label'] ?? $item['name'] ?? null;
    }

    /**
     * Bônus de poder no duelo por raridade (item equipado).
     *
     * @var array<string, float>
     */
    public const COMBAT_BONUS_BY_RARITY = [
        'common' => 0.012,
        'uncommon' => 0.02,
        'rare' => 0.03,
        'epic' => 0.04,
    ];

    public const COMBAT_BONUS_CAP = 0.10;

    public static function rarityLabel(string $rarity): string
    {
        return self::RARITIES[$rarity] ?? $rarity;
    }

    public static function combatBonusFromPercent(mixed $percent, string $rarity): float
    {
        if ($percent === null || $percent === '') {
            return self::COMBAT_BONUS_BY_RARITY[$rarity] ?? 0.0;
        }

        return round(max(0, min(self::COMBAT_BONUS_CAP, (float) $percent / 100)), 4);
    }

    /**
     * @param  array{rarity?: string, combat_bonus?: ?float}|null  $item
     */
    public static function combatBonusForItem(?array $item): float
    {
        if (! $item) {
            return 0.0;
        }

        if (array_key_exists('combat_bonus', $item) && $item['combat_bonus'] !== null) {
            return (float) $item['combat_bonus'];
        }

        return self::COMBAT_BONUS_BY_RARITY[$item['rarity'] ?? ''] ?? 0.0;
    }

    public static function combatBonusForKey(string $key): float
    {
        return self::combatBonusForItem(self::item($key));
    }

    /**
     * @return array{bonus: float, items: list<array{key: string, name: string, bonus: float}>}
     */
    public static function equippedCombatBonus(object $enrollment): array
    {
        $bonus = 0.0;
        $items = [];

        foreach (self::loadoutFromEnrollment($enrollment) as $key) {
            if (! filled($key) || ! self::has($key)) {
                continue;
            }

            $amount = self::combatBonusForKey($key);
            if ($amount <= 0) {
                continue;
            }

            $item = self::item($key);
            $bonus += $amount;
            $items[] = [
                'key' => $key,
                'name' => $item['name'],
                'bonus' => $amount,
            ];
        }

        return [
            'bonus' => round(min(self::COMBAT_BONUS_CAP, $bonus), 4),
            'items' => $items,
        ];
    }
}
