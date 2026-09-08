<?php

namespace App\Support;

class CosmeticCatalog
{
    public const SLOT_FRAME = 'frame';

    public const SLOT_ACCESSORY = 'accessory';

    public const SLOT_TITLE = 'title';

    public const SLOT_AURA = 'aura';

    public const CURRENCY_RELICS = 'relics';

    public const CURRENCY_SEALS = 'seals';

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
     * Catálogo curado de cosméticos (só visual).
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

    /**
     * @return array{slot: string, name: string, price: int, rarity: string, icon: string, currency?: string, css?: string, label?: string}|null
     */
    public static function item(string $key): ?array
    {
        return self::ITEMS[$key] ?? null;
    }

    public static function icon(string $key): string
    {
        return self::item($key)['icon'] ?? '✦';
    }

    public static function has(string $key): bool
    {
        return isset(self::ITEMS[$key]);
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

    public static function currencyLabel(string $currency): string
    {
        return match ($currency) {
            self::CURRENCY_SEALS => 'Selos',
            default => 'Relíquias',
        };
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
    public static function keysForSlot(string $slot): array
    {
        $keys = [];

        foreach (self::ITEMS as $key => $item) {
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

    public static function rarityLabel(string $rarity): string
    {
        return match ($rarity) {
            'common' => 'Comum',
            'uncommon' => 'Incomum',
            'rare' => 'Raro',
            'epic' => 'Épico',
            default => $rarity,
        };
    }
}
