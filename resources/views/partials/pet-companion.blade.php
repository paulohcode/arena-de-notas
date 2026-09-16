@php
    $pet = $pet ?? null;
@endphp
@if($pet)
    <div class="pet-companion flex items-center gap-2 {{ ($size ?? 'md') === 'lg' ? 'pet-companion--lg' : '' }}">
        @include('partials.pet-sprite', [
            'spriteKey' => $pet['sprite_key'] ?? 'owl',
            'gifUrl' => $pet['gif_url'] ?? null,
            'auraColor' => $pet['aura_color'] ?? null,
            'name' => $pet['custom_name'] ?? null,
            'size' => $size ?? 'md',
        ])
        <div class="min-w-0">
            <p class="font-display text-amber-200 truncate {{ ($size ?? 'md') === 'sm' ? 'text-sm' : 'text-base' }}">{{ $pet['custom_name'] ?? 'Mascote' }}</p>
            @if(($showBonus ?? true) && isset($pet['combat_bonus_percent']))
                <p class="text-xs text-emerald-300/80">+{{ number_format((float) $pet['combat_bonus_percent'], 1) }}% poder</p>
            @endif
        </div>
    </div>
@endif
