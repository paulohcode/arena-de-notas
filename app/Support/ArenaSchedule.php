<?php

namespace App\Support;

use Carbon\CarbonInterface;

class ArenaSchedule
{
    /**
     * @var array<int, string>
     */
    public const WEEKDAY_LABELS = [
        1 => 'Segunda',
        2 => 'Terça',
        3 => 'Quarta',
        4 => 'Quinta',
        5 => 'Sexta',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    /**
     * @return list<int>
     */
    public static function weekdays(): array
    {
        return [1, 2, 3, 4, 5, 6, 7];
    }

    public static function todayWeekday(?CarbonInterface $now = null): int
    {
        return ($now ?? now())
            ->copy()
            ->timezone((string) config('app.display_timezone'))
            ->isoWeekday();
    }

    public static function todayLabel(?CarbonInterface $now = null): string
    {
        return self::WEEKDAY_LABELS[self::todayWeekday($now)];
    }

    /**
     * @param  array<int|string, mixed>|null  $schedule
     * @return array{open: bool, cooldown_minutes: int, daily_limit: int}
     */
    public static function forWeekday(
        ?array $schedule,
        int $weekday,
        bool $fallbackOpen,
        int $fallbackCooldownMinutes,
        int $fallbackDailyLimit,
    ): array {
        $defaults = [
            'open' => $fallbackOpen,
            'cooldown_minutes' => max(0, $fallbackCooldownMinutes),
            'daily_limit' => max(1, $fallbackDailyLimit),
        ];

        $day = $schedule[$weekday] ?? $schedule[(string) $weekday] ?? null;

        if (! is_array($day)) {
            return $defaults;
        }

        return [
            'open' => self::boolean($day['open'] ?? $defaults['open']),
            'cooldown_minutes' => max(0, (int) ($day['cooldown_minutes'] ?? $defaults['cooldown_minutes'])),
            'daily_limit' => max(1, (int) ($day['daily_limit'] ?? $defaults['daily_limit'])),
        ];
    }

    /**
     * @param  array<int|string, mixed>|null  $schedule
     * @return array{open: bool, cooldown_minutes: int, daily_limit: int}
     */
    public static function forToday(
        ?array $schedule,
        bool $fallbackOpen,
        int $fallbackCooldownMinutes,
        int $fallbackDailyLimit,
        ?CarbonInterface $now = null,
    ): array {
        return self::forWeekday(
            $schedule,
            self::todayWeekday($now),
            $fallbackOpen,
            $fallbackCooldownMinutes,
            $fallbackDailyLimit,
        );
    }

    /**
     * @param  array<int|string, mixed>|null  $schedule
     * @return array<int, array{open: bool, cooldown_minutes: int, daily_limit: int}>
     */
    public static function week(
        ?array $schedule,
        bool $fallbackOpen,
        int $fallbackCooldownMinutes,
        int $fallbackDailyLimit,
    ): array {
        $week = [];

        foreach (self::weekdays() as $weekday) {
            $week[$weekday] = self::forWeekday(
                $schedule,
                $weekday,
                $fallbackOpen,
                $fallbackCooldownMinutes,
                $fallbackDailyLimit,
            );
        }

        return $week;
    }

    /**
     * @param  array<int|string, mixed>  $days
     * @return array<int, array{open: bool, cooldown_minutes: int, daily_limit: int}>
     */
    public static function fromValidated(array $days): array
    {
        return self::week($days, false, 0, 1);
    }

    /**
     * @param  array<int|string, mixed>|null  $schedule
     * @return array<int, array{open: bool, cooldown_minutes: int, daily_limit: int}>
     */
    public static function toggleToday(
        ?array $schedule,
        bool $open,
        bool $fallbackOpen,
        int $fallbackCooldownMinutes,
        int $fallbackDailyLimit,
        ?CarbonInterface $now = null,
    ): array {
        $week = self::week($schedule, $fallbackOpen, $fallbackCooldownMinutes, $fallbackDailyLimit);
        $week[self::todayWeekday($now)]['open'] = $open;

        return $week;
    }

    public static function cooldownLabel(int $minutes): string
    {
        $minutes = max(0, $minutes);

        if ($minutes === 0) {
            return 'sem espera';
        }

        if ($minutes % 60 === 0) {
            $hours = intdiv($minutes, 60);

            return $hours === 1 ? '1 hora' : $hours.' horas';
        }

        return $minutes === 1 ? '1 minuto' : $minutes.' minutos';
    }

    /**
     * @return array<string, list<string>>
     */
    public static function rules(string $prefix): array
    {
        $rules = [
            $prefix => ['required', 'array'],
        ];

        foreach (self::weekdays() as $weekday) {
            $rules[$prefix.'.'.$weekday] = ['required', 'array'];
            $rules[$prefix.'.'.$weekday.'.open'] = ['required', 'boolean'];
            $rules[$prefix.'.'.$weekday.'.cooldown_minutes'] = ['required', 'integer', 'min:0', 'max:10080'];
            $rules[$prefix.'.'.$weekday.'.daily_limit'] = ['required', 'integer', 'min:1', 'max:50'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $prefix, bool $realm = false): array
    {
        $messages = [
            $prefix.'.required' => 'Informe a configuração de cada dia da semana.',
            $prefix.'.array' => 'Informe a configuração de cada dia da semana.',
        ];

        $openRequired = $realm
            ? 'Informe se a arena entre turmas está aberta ou fechada.'
            : 'Informe se a arena está aberta ou fechada.';
        $openBoolean = $realm
            ? 'O status da arena entre turmas precisa ser aberto ou fechado.'
            : 'O status da arena precisa ser aberto ou fechado.';
        $cooldownRequired = $realm
            ? 'Informe o tempo de espera entre desafios do reino.'
            : 'Informe o tempo de espera entre batalhas.';
        $limitRequired = $realm
            ? 'Informe quantos duelos do reino são permitidos no dia.'
            : 'Informe quantas batalhas são permitidas no dia.';
        $limitInteger = $realm
            ? 'A quantidade de duelos precisa ser um número inteiro.'
            : 'A quantidade de batalhas precisa ser um número inteiro.';
        $limitMin = $realm
            ? 'É preciso permitir pelo menos 1 duelo do reino por dia.'
            : 'É preciso permitir pelo menos 1 batalha por dia.';
        $limitMax = $realm
            ? 'O limite diário não pode passar de 50 duelos.'
            : 'O limite diário não pode passar de 50 batalhas.';

        foreach (self::weekdays() as $weekday) {
            $messages[$prefix.'.'.$weekday.'.required'] = 'Informe a configuração de cada dia da semana.';
            $messages[$prefix.'.'.$weekday.'.array'] = 'Informe a configuração de cada dia da semana.';
            $messages[$prefix.'.'.$weekday.'.open.required'] = $openRequired;
            $messages[$prefix.'.'.$weekday.'.open.boolean'] = $openBoolean;
            $messages[$prefix.'.'.$weekday.'.cooldown_minutes.required'] = $cooldownRequired;
            $messages[$prefix.'.'.$weekday.'.cooldown_minutes.integer'] = 'O tempo de espera precisa ser um número inteiro de minutos.';
            $messages[$prefix.'.'.$weekday.'.cooldown_minutes.min'] = 'O tempo de espera não pode ser negativo.';
            $messages[$prefix.'.'.$weekday.'.cooldown_minutes.max'] = 'O tempo de espera não pode passar de 7 dias.';
            $messages[$prefix.'.'.$weekday.'.daily_limit.required'] = $limitRequired;
            $messages[$prefix.'.'.$weekday.'.daily_limit.integer'] = $limitInteger;
            $messages[$prefix.'.'.$weekday.'.daily_limit.min'] = $limitMin;
            $messages[$prefix.'.'.$weekday.'.daily_limit.max'] = $limitMax;
        }

        return $messages;
    }

    public static function hasSchedule(?array $schedule): bool
    {
        return is_array($schedule) && $schedule !== [];
    }

    private static function boolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
