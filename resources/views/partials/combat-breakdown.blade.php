@php
    $breakdown = $breakdown ?? [];
    $percent = fn (float $value): string => number_format($value * 100, 1).'%';
@endphp
@if($breakdown)
    <ul class="text-xs text-amber-100/55 space-y-1 mt-2 text-left">
        <li>Nível {{ $breakdown['level_name'] ?? '—' }} × {{ number_format((float) ($breakdown['level_mult'] ?? 1), 2) }}</li>
        <li>Notas {{ number_format((float) ($breakdown['grade'] ?? 0), 1) }} → +{{ $percent((float) ($breakdown['grade_bonus'] ?? 0)) }}</li>
        <li>Frequência {{ number_format((float) ($breakdown['attendance'] ?? 0), 1) }} → +{{ $percent((float) ($breakdown['attendance_bonus'] ?? 0)) }}</li>
        <li>Guilda {{ number_format((float) ($breakdown['team'] ?? 0), 1) }} → +{{ $percent((float) ($breakdown['team_bonus'] ?? 0)) }}</li>
        <li>
            Itens +{{ $percent((float) ($breakdown['gear_bonus'] ?? 0)) }}
            @if(! empty($breakdown['gear_items']))
                ({{ collect($breakdown['gear_items'])->pluck('name')->join(', ') }})
            @else
                (nenhum equipado)
            @endif
        </li>
    </ul>
@endif
