@php
    $portraitKey = $avatarKey ?? $student->character_avatar;
    $sizeClass = ($size ?? 'md') === 'lg' ? 'hero-portrait--lg' : (($size ?? 'md') === 'sm' ? 'hero-portrait--sm' : '');
@endphp
<span class="hero-portrait {{ $sizeClass }} {{ $student->characterAuraClass(true) }}" style="--portrait-tone: {{ $student->avatarTone($portraitKey) }}" aria-hidden="true">
    @include('partials.class-fx', ['characterClass' => $student->character_class])
    <span>{{ $student->avatarIcon($portraitKey) }}</span>
</span>
