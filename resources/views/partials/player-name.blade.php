@php
    use App\Support\CosmeticCatalog;

    $showPending = $showPending ?? false;

    $loadout = $cosmetics ?? null;
    if ($loadout === null && isset($enrollment) && $enrollment) {
        $loadout = is_array($enrollment)
            ? $enrollment
            : CosmeticCatalog::loadoutFromEnrollment($enrollment);
    } elseif ($loadout === null && isset($student->pivot)) {
        $loadout = CosmeticCatalog::loadoutFromEnrollment($student->pivot);
    }

    $titleLabel = CosmeticCatalog::titleLabel($loadout['title'] ?? null);
@endphp
<span class="inline-flex items-center gap-2 min-w-0">
    @include('partials.player-avatar', [
        'student' => $student,
        'size' => $size ?? 'sm',
        'enrollment' => $enrollment ?? null,
        'cosmetics' => $loadout,
        'avatarKey' => $avatarKey ?? null,
    ])
    <span class="min-w-0 truncate">
        <span>{{ $student->name }}</span>
        @if($student->arenaName())
            <span class="text-amber-300"> · {{ $student->arenaName() }}</span>
        @elseif($showPending && $student->isPersonaPending())
            <span class="text-amber-100/50 text-xs"> · aguardando aprovação</span>
        @endif
        @if($titleLabel)
            <span class="cosmetic-title cosmetic-title--inline">{{ $titleLabel }}</span>
        @endif
    </span>
</span>
