<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class GameCurrency extends Model
{
    public const KEY_RELICS = 'relics';

    public const KEY_SEALS = 'seals';

    public const KEY_AURAS = 'auras';

    public const KEY_GLORY = 'glory';

    /**
     * @var array<string, array{name: string, icon: string, sort_order: int, blurb: string}>
     */
    public const DEFAULTS = [
        self::KEY_RELICS => [
            'name' => 'Relíquias',
            'icon' => '💠',
            'sort_order' => 1,
            'blurb' => 'Ganhas na arena da turma. Compram itens da loja da turma e do mercado entre alunos.',
        ],
        self::KEY_SEALS => [
            'name' => 'Selos',
            'icon' => '💮',
            'sort_order' => 2,
            'blurb' => 'Ganhas na chamada (presente). Compram itens de presença, que não entram no mercado.',
        ],
        self::KEY_AURAS => [
            'name' => 'Aura',
            'icon' => '✨',
            'sort_order' => 3,
            'blurb' => 'Ganhas em duelos entre turmas do mesmo reino. Compram itens únicos daquele reino.',
        ],
        self::KEY_GLORY => [
            'name' => 'Glória',
            'icon' => '🏆',
            'sort_order' => 4,
            'blurb' => 'Placar dos duelos RPG e das guerras de guilda. Não altera a média nem o XP.',
        ],
    ];

    /**
     * @var list<string>
     */
    public const SHOP_KEYS = [
        self::KEY_RELICS,
        self::KEY_SEALS,
        self::KEY_AURAS,
    ];

    /**
     * @var list<string>
     */
    public const ICON_SUGGESTIONS = [
        '💠', '💎', '🪙', '⚱️', '🔮', '💮', '🛡️', '🎫', '✨', '🌌', '⚡', '🏆', '⭐', '🔥',
    ];

    protected $fillable = [
        'name',
        'icon',
    ];

    /**
     * @var Collection<string, self>|null
     */
    private static ?Collection $cached = null;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return Collection<string, self>
     */
    public static function catalog(): Collection
    {
        return self::$cached ??= self::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->keyBy('key');
    }

    /**
     * @return Collection<int, self>
     */
    public static function ordered(): Collection
    {
        return self::catalog()->values();
    }

    public static function flush(): void
    {
        self::$cached = null;
    }

    public static function label(string $key): string
    {
        return self::catalog()->get($key)?->name
            ?? self::DEFAULTS[$key]['name']
            ?? $key;
    }

    public static function icon(string $key): string
    {
        return self::catalog()->get($key)?->icon
            ?? self::DEFAULTS[$key]['icon']
            ?? '';
    }

    public static function blurb(string $key): string
    {
        return self::DEFAULTS[$key]['blurb'] ?? '';
    }

    public static function format(string $key, int|string|null $amount = null, bool $withIcon = true): string
    {
        $parts = [];

        if ($withIcon) {
            $icon = self::icon($key);
            if ($icon !== '') {
                $parts[] = $icon;
            }
        }

        if ($amount !== null) {
            $parts[] = (string) $amount;
        }

        $parts[] = self::label($key);

        return implode(' ', $parts);
    }

    /**
     * @return array<string, string>
     */
    public static function shopLabels(): array
    {
        $labels = [];

        foreach (self::SHOP_KEYS as $key) {
            $labels[$key] = trim(self::icon($key).' '.self::label($key));
        }

        return $labels;
    }

    /**
     * @param  array<string, array{name?: mixed, icon?: mixed}>  $currencies
     */
    public static function syncCatalog(array $currencies): void
    {
        foreach (array_keys(self::DEFAULTS) as $key) {
            $attributes = $currencies[$key] ?? null;
            if (! is_array($attributes)) {
                continue;
            }

            self::query()->where('key', $key)->update([
                'name' => (string) ($attributes['name'] ?? self::DEFAULTS[$key]['name']),
                'icon' => (string) ($attributes['icon'] ?? self::DEFAULTS[$key]['icon']),
            ]);
        }

        self::flush();
    }

    /**
     * @return array<string, list<string>>
     */
    public static function updateRules(): array
    {
        $rules = [
            'currencies' => ['required', 'array'],
        ];

        foreach (array_keys(self::DEFAULTS) as $key) {
            $rules["currencies.{$key}.name"] = ['required', 'string', 'max:40'];
            $rules["currencies.{$key}.icon"] = ['required', 'string', 'max:32'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function updateMessages(): array
    {
        $messages = [
            'currencies.required' => 'Informe as moedas da arena.',
        ];

        foreach (self::DEFAULTS as $key => $defaults) {
            $messages["currencies.{$key}.name.required"] = "Informe o nome de {$defaults['name']}.";
            $messages["currencies.{$key}.name.max"] = "O nome de {$defaults['name']} pode ter no máximo 40 caracteres.";
            $messages["currencies.{$key}.icon.required"] = "Informe o ícone de {$defaults['name']}.";
            $messages["currencies.{$key}.icon.max"] = "O ícone de {$defaults['name']} pode ter no máximo 32 caracteres.";
        }

        return $messages;
    }
}
