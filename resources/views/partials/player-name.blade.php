@php
    $showPending = $showPending ?? false;
@endphp
<span class="inline-flex items-center gap-2 min-w-0">
    @include('partials.player-avatar', ['student' => $student, 'size' => $size ?? 'sm'])
    <span class="min-w-0 truncate">
        <span>{{ $student->name }}</span>
        @if($student->arenaName())
            <span class="text-amber-300"> · {{ $student->arenaName() }}</span>
        @elseif($showPending && $student->isPersonaPending())
            <span class="text-amber-100/50 text-xs"> · aguardando aprovação</span>
        @endif
    </span>
</span>
