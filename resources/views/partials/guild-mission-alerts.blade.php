@if(($guildMissionAlerts ?? collect())->isNotEmpty())
    <div class="{{ $guildAlertClass ?? 'mt-4 p-3 rounded-xl border border-amber-400/40 bg-amber-500/10' }}" role="alert">
        <p class="font-semibold text-amber-200 text-sm">Pendências da guilda</p>
        <ul class="mt-2 space-y-1">
            @foreach($guildMissionAlerts as $alert)
                @if($alert['guild_wide'])
                    <li class="text-sm text-amber-100/85">A guilda ainda não concluiu a missão {{ $alert['activity']->name }}.</li>
                @else
                    @foreach($alert['students'] as $member)
                        <li class="text-sm text-amber-100/85">{{ $member->name }} ainda não concluiu a missão {{ $alert['activity']->name }}.</li>
                    @endforeach
                @endif
            @endforeach
        </ul>
    </div>
@endif
