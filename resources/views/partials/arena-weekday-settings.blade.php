@php
    $prefix = $prefix ?? 'days';
    $week = $week ?? [];
    $todayWeekday = \App\Support\ArenaSchedule::todayWeekday();
    $limitLabel = $limitLabel ?? 'Batalhas no dia';
    $cooldownHint = $cooldownHint ?? '0 = sem espera. Ex.: 30 para meia hora, 120 para 2 horas.';
@endphp
<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-amber-100/55">
                <th class="py-2 pr-3 font-medium">Dia</th>
                <th class="py-2 pr-3 font-medium">Estado</th>
                <th class="py-2 pr-3 font-medium">Espera (minutos)</th>
                <th class="py-2 font-medium">{{ $limitLabel }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-purple-900/40">
            @foreach(\App\Support\ArenaSchedule::WEEKDAY_LABELS as $weekday => $label)
                @php
                    $day = $week[$weekday] ?? ['open' => false, 'cooldown_minutes' => 0, 'daily_limit' => 1];
                    $isToday = $weekday === $todayWeekday;
                @endphp
                <tr class="{{ $isToday ? 'bg-amber-400/10' : '' }}">
                    <td class="py-3 pr-3 align-top">
                        <span class="{{ $isToday ? 'text-amber-200 font-semibold' : 'text-amber-100/80' }}">{{ $label }}</span>
                        @if($isToday)
                            <span class="block text-[11px] text-cyan-300/80">hoje</span>
                        @endif
                    </td>
                    <td class="py-3 pr-3 align-top min-w-36">
                        <select class="game-select w-full" name="{{ $prefix }}[{{ $weekday }}][open]" required>
                            <option value="1" @selected((string) old($prefix.'.'.$weekday.'.open', $day['open'] ? '1' : '0') === '1')>Aberta</option>
                            <option value="0" @selected((string) old($prefix.'.'.$weekday.'.open', $day['open'] ? '1' : '0') === '0')>Fechada</option>
                        </select>
                    </td>
                    <td class="py-3 pr-3 align-top min-w-32">
                        <input class="game-input w-full" type="number" name="{{ $prefix }}[{{ $weekday }}][cooldown_minutes]" min="0" max="10080" required
                            value="{{ old($prefix.'.'.$weekday.'.cooldown_minutes', $day['cooldown_minutes']) }}">
                    </td>
                    <td class="py-3 align-top min-w-28">
                        <input class="game-input w-full" type="number" name="{{ $prefix }}[{{ $weekday }}][daily_limit]" min="1" max="50" required
                            value="{{ old($prefix.'.'.$weekday.'.daily_limit', $day['daily_limit']) }}">
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<p class="text-xs text-amber-100/50">{{ $cooldownHint }} O limite vale só naquele dia da semana.</p>
