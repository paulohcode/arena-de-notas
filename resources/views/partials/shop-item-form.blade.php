@php
    $item = $item ?? null;
    $editing = $item !== null;
    $submitLabel = $submit ?? ($editing ? 'Salvar alterações' : 'Cadastrar item');
@endphp
<form method="POST" action="{{ $action }}" class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
    @csrf
    @if($editing)
        @method('PUT')
    @endif
    <label class="block">
        <span class="text-sm">Nome</span>
        <input class="game-input mt-1 w-full" name="name" value="{{ old('name', $item?->name) }}" required maxlength="60" placeholder="Ex: Capa da Turma">
    </label>
    <label class="block">
        <span class="text-sm">Tipo</span>
        <select class="game-select mt-1 w-full" name="slot" required>
            @foreach($slots as $slot => $slotLabel)
                <option value="{{ $slot }}" @selected(old('slot', $item->slot ?? 'accessory') === $slot)>{{ $slotLabel }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="text-sm">Ícone</span>
        <input class="game-input mt-1 w-full" name="icon" value="{{ old('icon', $item->icon ?? '🛡️') }}" required maxlength="32" placeholder="🛡️">
        <span class="block text-xs text-amber-100/50 mt-1">Use um emoji. Ex.: 🛡️ 🎩 🌟 🐉</span>
    </label>
    <label class="block">
        <span class="text-sm">Preço</span>
        <input class="game-input mt-1 w-full" type="number" name="price" min="1" max="9999" value="{{ old('price', $item->price ?? 40) }}" required>
    </label>
    <label class="block">
        <span class="text-sm">Moeda</span>
        <select class="game-select mt-1 w-full" name="currency" required>
            @foreach($currencies as $currency => $currencyLabel)
                <option value="{{ $currency }}" @selected(old('currency', $item->currency ?? 'relics') === $currency)>{{ $currencyLabel }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="text-sm">Raridade</span>
        <select class="game-select mt-1 w-full" name="rarity" required>
            @foreach($rarities as $rarity => $rarityLabel)
                <option value="{{ $rarity }}" @selected(old('rarity', $item->rarity ?? 'common') === $rarity)>{{ $rarityLabel }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="text-sm">Visual (opcional)</span>
        <select class="game-select mt-1 w-full" name="css">
            <option value="">Padrão</option>
            @foreach($cssTones as $tone => $toneLabel)
                <option value="{{ $tone }}" @selected(old('css', $item->css ?? '') === $tone)>{{ $toneLabel }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="text-sm">Título exibido (opcional)</span>
        <input class="game-input mt-1 w-full" name="label" value="{{ old('label', $item?->label) }}" maxlength="60" placeholder="Usado em títulos">
    </label>
    <label class="block">
        <span class="text-sm">Poder no duelo (%)</span>
        <input class="game-input mt-1 w-full" type="number" name="combat_bonus_percent" min="0" max="10" step="0.1" value="{{ old('combat_bonus_percent', $item?->combatBonusPercent() ?? 1.2) }}">
        <span class="block text-xs text-amber-100/50 mt-1">Quanto o item soma no poder quando está equipado. O total de todos os itens não passa de 10%.</span>
    </label>
    @unless($editing)
        <label class="block">
            <span class="text-sm">Estoque inicial</span>
            <input class="game-input mt-1 w-full" type="number" name="stock" min="0" max="99" value="{{ old('stock', 1) }}">
        </label>
    @endunless
    <div class="md:col-span-2 lg:col-span-3 flex flex-wrap items-center gap-3">
        <button class="game-btn" type="submit">{{ $submitLabel }}</button>
        @isset($cancel)
            <a class="game-btn-ghost" href="{{ $cancel }}">Cancelar</a>
        @endisset
    </div>
</form>
