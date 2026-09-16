@php
    use App\Support\CosmeticCatalog;

    $loadout = CosmeticCatalog::resolveLoadout(
        $student ?? null,
        $enrollment ?? null,
        $cosmetics ?? null,
    );
    $label = CosmeticCatalog::titleLabel($loadout['title'] ?? null);
    $inline = ($inline ?? true);
@endphp
@if($label)
    <span class="cosmetic-title {{ $inline ? 'cosmetic-title--inline' : '' }}">{{ $label }}</span>
@endif
