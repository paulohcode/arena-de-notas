@php
    $icon = $icon ?? '✦';
    $rarity = $rarity ?? 'common';
    $css = $css ?? null;
    $slot = $slot ?? null;
    $size = $size ?? 'md';
@endphp
<div
    class="shop-item-art shop-item-art--{{ $size }} shop-item-art--{{ $rarity }} {{ $css ? 'shop-item-art--tone-'.$css : '' }} {{ $slot ? 'shop-item-art--'.$slot : '' }}"
    aria-hidden="true"
>
    <span class="shop-item-art__glow"></span>
    <span class="shop-item-art__icon">{{ $icon }}</span>
</div>
