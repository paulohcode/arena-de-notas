@php
    $sprite = $spriteKey ?? 'owl';
    $aura = $auraColor ?? null;
    $gif = $gifUrl ?? null;
    $size = $size ?? 'md';
    $name = $name ?? null;
    $sizeClass = $size === 'lg' ? 'pet-sprite--lg' : ($size === 'sm' ? 'pet-sprite--sm' : '');
    $auraClass = $aura ? 'pet-aura pet-aura--'.$aura : '';
@endphp
<span class="pet-sprite pet-sprite--{{ $sprite }} {{ $sizeClass }} {{ $auraClass }}" @if($name) title="{{ $name }}" @endif>
    @if($gif)
        <img src="{{ $gif }}" alt="{{ $name ?? 'Mascote' }}" class="pet-sprite__gif">
    @else
        <span class="pet-sprite__body" aria-hidden="true"></span>
        <span class="pet-sprite__wing pet-sprite__wing--left" aria-hidden="true"></span>
        <span class="pet-sprite__wing pet-sprite__wing--right" aria-hidden="true"></span>
        <span class="pet-sprite__eye" aria-hidden="true"></span>
    @endif
</span>
