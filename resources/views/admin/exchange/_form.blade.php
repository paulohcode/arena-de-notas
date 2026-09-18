@php
    $receiveCurrency = old('receive_currency', $rate?->receive_currency ?? 'auras');
    $receiveAmount = old('receive_amount', $rate?->receive_amount ?? 100);
    $payCurrency = old('pay_currency', $rate?->pay_currency ?? 'relics');
    $payAmount = old('pay_amount', $rate?->pay_amount ?? 15);
@endphp

<div class="grid sm:grid-cols-2 gap-4">
    <label class="block">
        <span class="text-sm">Moeda que o aluno compra</span>
        <select class="game-select mt-1 w-full" name="receive_currency" required>
            @foreach($currencyOptions as $key => $label)
                <option value="{{ $key }}" @selected($receiveCurrency === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('receive_currency')
            <p class="text-sm text-rose-300 mt-1">{{ $message }}</p>
        @enderror
    </label>
    <label class="block">
        <span class="text-sm">Quantidade recebida</span>
        <input class="game-input mt-1 w-full" type="number" name="receive_amount" min="1" max="99999" value="{{ $receiveAmount }}" required>
        @error('receive_amount')
            <p class="text-sm text-rose-300 mt-1">{{ $message }}</p>
        @enderror
    </label>
    <label class="block">
        <span class="text-sm">Moeda de pagamento</span>
        <select class="game-select mt-1 w-full" name="pay_currency" required>
            @foreach($currencyOptions as $key => $label)
                <option value="{{ $key }}" @selected($payCurrency === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('pay_currency')
            <p class="text-sm text-rose-300 mt-1">{{ $message }}</p>
        @enderror
    </label>
    <label class="block">
        <span class="text-sm">Quantidade paga</span>
        <input class="game-input mt-1 w-full" type="number" name="pay_amount" min="1" max="99999" value="{{ $payAmount }}" required>
        @error('pay_amount')
            <p class="text-sm text-rose-300 mt-1">{{ $message }}</p>
        @enderror
    </label>
</div>
<p class="text-sm text-amber-100/50">Ex.: comprar 100 Aura pagando 15 Relíquias — ou outra oferta com 5 Selos.</p>
