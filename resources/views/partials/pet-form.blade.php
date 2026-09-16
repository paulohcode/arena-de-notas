@php
    $pet = $pet ?? null;
    $editing = $pet !== null;
    $submitLabel = $submit ?? ($editing ? 'Salvar alterações' : 'Cadastrar mascote');
    $enctype = 'multipart/form-data';
@endphp
<form method="POST" action="{{ $action }}" enctype="{{ $enctype }}" class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
    @csrf
    @if($editing)
        @method('PUT')
    @endif
    <label class="block">
        <span class="text-sm">Nome</span>
        <input class="game-input mt-1 w-full" name="name" value="{{ old('name', $pet?->name) }}" required maxlength="60" placeholder="Ex: Coruja Sábia">
    </label>
    <label class="block md:col-span-2">
        <span class="text-sm">Descrição</span>
        <input class="game-input mt-1 w-full" name="description" value="{{ old('description', $pet?->description) }}" maxlength="255" placeholder="Curta e épica">
    </label>
    <label class="block">
        <span class="text-sm">Raridade</span>
        <select class="game-select mt-1 w-full" name="rarity" required>
            @foreach($rarities as $rarity => $rarityLabel)
                <option value="{{ $rarity }}" @selected(old('rarity', $pet->rarity ?? 'common') === $rarity)>{{ $rarityLabel }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="text-sm">Sprite padrão</span>
        <select class="game-select mt-1 w-full" name="sprite_key">
            @foreach($spriteKeys as $sprite)
                <option value="{{ $sprite }}" @selected(old('sprite_key', $pet->sprite_key ?? 'owl') === $sprite)>{{ $sprite }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="text-sm">{{ \App\Models\GameCurrency::label('relics') }}</span>
        <input class="game-input mt-1 w-full" type="number" name="price_relics" min="0" max="9999" value="{{ old('price_relics', $pet->price_relics ?? \App\Support\PetCatalog::DEFAULT_FORM_RELICS) }}" required>
    </label>
    <label class="block">
        <span class="text-sm">{{ \App\Models\GameCurrency::label('seals') }}</span>
        <input class="game-input mt-1 w-full" type="number" name="price_seals" min="0" max="9999" value="{{ old('price_seals', $pet->price_seals ?? \App\Support\PetCatalog::DEFAULT_FORM_SEALS) }}" required>
    </label>
    <label class="block">
        <span class="text-sm">{{ \App\Models\GameCurrency::label('auras') }}</span>
        <input class="game-input mt-1 w-full" type="number" name="price_auras" min="0" max="9999" value="{{ old('price_auras', $pet->price_auras ?? \App\Support\PetCatalog::DEFAULT_FORM_AURAS) }}" required>
    </label>
    <label class="block">
        <span class="text-sm">Poder no duelo (%)</span>
        <input class="game-input mt-1 w-full" type="number" name="combat_bonus_percent" min="0" max="15" step="0.1" value="{{ old('combat_bonus_percent', $pet?->combatBonusPercent() ?? 2) }}" required>
    </label>
    @unless($editing)
        <label class="block">
            <span class="text-sm">Estoque inicial</span>
            <input class="game-input mt-1 w-full" type="number" name="stock" min="0" max="99" value="{{ old('stock', \App\Support\PetCatalog::DEFAULT_STOCK) }}">
        </label>
    @endunless
    <label class="block">
        <span class="text-sm">GIF / imagem animada</span>
        <input class="game-input mt-1 w-full" type="file" name="gif" accept=".gif,.webp,.png,image/gif,image/webp,image/png">
        <span class="block text-xs text-amber-100/50 mt-1">Opcional. Até 2 MB. Sem arquivo, usa o sprite animado.</span>
    </label>
    @if($editing)
        <label class="flex items-center gap-2 mt-6">
            <input type="checkbox" name="active" value="1" @checked(old('active', $pet->active))>
            <span class="text-sm">Ativo na loja</span>
        </label>
    @endif
    <div class="md:col-span-2 lg:col-span-3 flex flex-wrap items-center gap-3">
        <button class="game-btn" type="submit">{{ $submitLabel }}</button>
        @isset($cancel)
            <a class="game-btn-ghost" href="{{ $cancel }}">Cancelar</a>
        @endisset
    </div>
</form>
