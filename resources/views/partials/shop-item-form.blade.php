<form method="POST" action="{{ $action }}" class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
    @csrf
    <label class="block">
        <span class="text-sm">Nome</span>
        <input class="game-input mt-1 w-full" name="name" value="{{ old('name') }}" required maxlength="60" placeholder="Ex: Capa da Turma">
    </label>
    <label class="block">
        <span class="text-sm">Tipo</span>
        <select class="game-select mt-1 w-full" name="slot" required>
            @foreach($slots as $slot => $slotLabel)
                <option value="{{ $slot }}" @selected(old('slot', 'accessory') === $slot)>{{ $slotLabel }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="text-sm">Ícone</span>
        <input class="game-input mt-1 w-full" name="icon" value="{{ old('icon', '🛡️') }}" required maxlength="32" placeholder="🛡️">
        <span class="block text-xs text-amber-100/50 mt-1">Use um emoji. Ex.: 🛡️ 🎩 🌟 🐉</span>
    </label>
    <label class="block">
        <span class="text-sm">Preço</span>
        <input class="game-input mt-1 w-full" type="number" name="price" min="1" max="9999" value="{{ old('price', 40) }}" required>
    </label>
    <label class="block">
        <span class="text-sm">Moeda</span>
        <select class="game-select mt-1 w-full" name="currency" required>
            @foreach($currencies as $currency => $currencyLabel)
                <option value="{{ $currency }}" @selected(old('currency', 'relics') === $currency)>{{ $currencyLabel }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="text-sm">Raridade</span>
        <select class="game-select mt-1 w-full" name="rarity" required>
            @foreach($rarities as $rarity => $rarityLabel)
                <option value="{{ $rarity }}" @selected(old('rarity', 'common') === $rarity)>{{ $rarityLabel }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="text-sm">Visual (opcional)</span>
        <select class="game-select mt-1 w-full" name="css">
            <option value="">Padrão</option>
            @foreach($cssTones as $tone => $toneLabel)
                <option value="{{ $tone }}" @selected(old('css') === $tone)>{{ $toneLabel }}</option>
            @endforeach
        </select>
    </label>
    <label class="block">
        <span class="text-sm">Título exibido (opcional)</span>
        <input class="game-input mt-1 w-full" name="label" value="{{ old('label') }}" maxlength="60" placeholder="Usado em títulos">
    </label>
    <label class="block">
        <span class="text-sm">Estoque inicial</span>
        <input class="game-input mt-1 w-full" type="number" name="stock" min="0" max="99" value="{{ old('stock', 1) }}">
    </label>
    <div class="md:col-span-2 lg:col-span-3">
        <button class="game-btn" type="submit">Cadastrar item</button>
    </div>
</form>
