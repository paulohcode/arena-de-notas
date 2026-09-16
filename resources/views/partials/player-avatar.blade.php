@php
    use App\Support\CosmeticCatalog;

    $portraitKey = $avatarKey ?? $student->character_avatar;
    $sizeClass = ($size ?? 'md') === 'lg' ? 'hero-portrait--lg' : (($size ?? 'md') === 'sm' ? 'hero-portrait--sm' : '');

    $loadout = CosmeticCatalog::resolveLoadout(
        $student ?? null,
        $enrollment ?? null,
        $cosmetics ?? null,
    );

    $frameKey = $loadout['frame'] ?? null;
    $accessoryKey = $loadout['accessory'] ?? null;
    $auraKey = $loadout['aura'] ?? null;

    $frameCss = filled($frameKey) ? (CosmeticCatalog::item($frameKey)['css'] ?? null) : null;
    $auraCss = filled($auraKey) ? (CosmeticCatalog::item($auraKey)['css'] ?? null) : null;
    $accessoryIcon = filled($accessoryKey) ? (CosmeticCatalog::item($accessoryKey)['icon'] ?? null) : null;

    $portraitClasses = trim(implode(' ', array_filter([
        'hero-portrait',
        $sizeClass,
        $student->characterAuraClass(true),
        $frameCss ? 'cosmetic-frame cosmetic-frame--'.$frameCss : null,
        $auraCss ? 'cosmetic-aura cosmetic-aura--'.$auraCss : null,
    ])));
@endphp
<span class="{{ $portraitClasses }}" style="--portrait-tone: {{ $student->avatarTone($portraitKey) }}" aria-hidden="true">
    @include('partials.class-fx', ['characterClass' => $student->character_class])
    <span>{{ $student->avatarIcon($portraitKey) }}</span>
    @if(filled($accessoryIcon))
        <span class="cosmetic-accessory">{{ $accessoryIcon }}</span>
    @endif
</span>
