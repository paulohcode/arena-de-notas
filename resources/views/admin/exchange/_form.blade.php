@php
    $currencyA = old('currency_a', $rate?->currency_a ?? 'relics');
    $currencyB = old('currency_b', $rate?->currency_b ?? 'seals');
    $amountA = old('amount_a', $rate?->amount_a ?? 17);
    $amountB = old('amount_b', $rate?->amount_b ?? 10);
@endphp

<div class="grid sm:grid-cols-2 gap-4">
    <label class="block">
        <span class="text-sm">Moeda A</span>
        <select class="game-select mt-1 w-full" name="currency_a" required>
            @foreach($currencyOptions as $key => $label)
                <option value="{{ $key }}" @selected($currencyA === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('currency_a')
            <p class="text-sm text-rose-300 mt-1">{{ $message }}</p>
        @enderror
    </label>
    <label class="block">
        <span class="text-sm">Quantidade A</span>
        <input class="game-input mt-1 w-full" type="number" name="amount_a" min="1" max="99999" value="{{ $amountA }}" required>
        @error('amount_a')
            <p class="text-sm text-rose-300 mt-1">{{ $message }}</p>
        @enderror
    </label>
    <label class="block">
        <span class="text-sm">Moeda B</span>
        <select class="game-select mt-1 w-full" name="currency_b" required>
            @foreach($currencyOptions as $key => $label)
                <option value="{{ $key }}" @selected($currencyB === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('currency_b')
            <p class="text-sm text-rose-300 mt-1">{{ $message }}</p>
        @enderror
    </label>
    <label class="block">
        <span class="text-sm">Quantidade B</span>
        <input class="game-input mt-1 w-full" type="number" name="amount_b" min="1" max="99999" value="{{ $amountB }}" required>
        @error('amount_b')
            <p class="text-sm text-rose-300 mt-1">{{ $message }}</p>
        @enderror
    </label>
</div>
<p class="text-sm text-amber-100/50">Ex.: 17 Relíquias = 10 Selos. A ordem das moedas é normalizada automaticamente.</p>
