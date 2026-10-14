@extends('layouts.game')

@section('title', 'Forja — '.$class->name)

@section('content')
<div class="flex flex-wrap items-start justify-between gap-4 mb-6 reveal">
    <div>
        <p class="hero-kicker !mb-1">Fundição da arena</p>
        <h1 class="font-display text-4xl text-amber-300">Forja</h1>
        <p class="text-amber-100/60 mt-1">
            {{ $class->name }} ·
            <span class="text-cyan-300">{{ \App\Models\GameCurrency::format('relics', $student->enrollmentIn($class)?->relics ?? 0) }}</span>
            ·
            <span class="text-emerald-300">{{ \App\Models\GameCurrency::format('seals', $student->enrollmentIn($class)?->seals ?? 0) }}</span>
        </p>
        <p class="text-sm text-amber-100/45 mt-2 max-w-2xl">
            Funda cópias do mesmo item para somar o poder e subir de nível.
            Taxa de {{ (int) round($feeRate * 100) }}% do valor de catálogo vai ao cofre do reino.
            Mínimo de {{ $minInputs }} itens por forja.
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a class="game-btn-ghost" href="{{ route('student.shop.index') }}">Loja</a>
        <a class="game-btn-ghost" href="{{ route('student.pets.index') }}">Mascotes</a>
        <a class="game-btn-ghost" href="{{ route('student.dashboard') }}">Minha ficha</a>
    </div>
</div>

@error('forge')
    <div class="game-card p-4 mb-6 text-rose-300 reveal">{{ $message }}</div>
@enderror
@error('fee')
    <div class="game-card p-4 mb-6 text-rose-300 reveal">{{ $message }}</div>
@enderror
@error('cosmetic_ids')
    <div class="game-card p-4 mb-6 text-rose-300 reveal">{{ $message }}</div>
@enderror
@error('pet_ids')
    <div class="game-card p-4 mb-6 text-rose-300 reveal">{{ $message }}</div>
@enderror

@unless($available)
    <div class="game-card p-6 text-center text-amber-100/60 reveal">
        A forja só funciona em turmas vinculadas a um reino.
    </div>
@else
    @php
        $cosmeticGroups = collect($cosmetics)->groupBy('item_key')->filter(fn ($rows) => $rows->count() >= $minInputs);
        $petGroups = collect($pets)->groupBy('pet_id')->filter(fn ($rows) => $rows->count() >= $minInputs);
    @endphp

    <section class="mb-10 reveal space-y-4">
        <div>
            <h2 class="font-display text-2xl text-amber-200">Cosméticos</h2>
            <p class="text-sm text-amber-100/55 mt-1">Selecione pelo menos {{ $minInputs }} cópias do mesmo item.</p>
        </div>

        @forelse($cosmeticGroups as $itemKey => $rows)
            @php $sample = $rows->first(); @endphp
            <article class="game-card p-5 space-y-4" data-forge-group="cosmetic-{{ $itemKey }}">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="font-display text-xl text-amber-100">{{ $sample['name'] }}</p>
                        <p class="text-sm text-amber-100/55">
                            {{ $sample['rarity_label'] }} · {{ $rows->count() }} cópia(s)
                        </p>
                    </div>
                    <p class="text-sm text-cyan-300" data-forge-preview>Selecione itens para ver o resultado</p>
                </div>
                <form method="POST" action="{{ route('student.forge.cosmetics') }}" class="space-y-4" data-forge-form data-unit-price="{{ \App\Support\CosmeticCatalog::item($itemKey)['price'] ?? 1 }}">
                    @csrf
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($rows as $row)
                            <label class="game-card p-3 flex gap-3 cursor-pointer border border-amber-500/10 hover:border-amber-400/40">
                                <input
                                    type="checkbox"
                                    name="cosmetic_ids[]"
                                    value="{{ $row['id'] }}"
                                    class="mt-1"
                                    data-forge-input
                                    data-level="{{ $row['forge_level'] }}"
                                    data-bonus="{{ $row['combat_bonus'] }}"
                                    @disabled($row['listed_price'] !== null)
                                >
                                <span class="min-w-0">
                                    <span class="block font-display text-amber-100">{{ $row['name'] }}</span>
                                    <span class="block text-xs text-orange-300/80 mt-1">{{ $row['forge_level_label'] }} · +{{ $row['combat_bonus_percent'] }}%</span>
                                    @if($row['css'])
                                        <span class="block text-xs text-amber-100/45 mt-1">Tom: {{ $row['css'] }}</span>
                                    @endif
                                    @if($row['listed_price'] !== null)
                                        <span class="block text-xs text-rose-300 mt-1">Anunciado — retire antes de forjar</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <button type="submit" class="game-btn" data-forge-submit disabled>Forjar selecionados</button>
                </form>
            </article>
        @empty
            <div class="game-card p-5 text-amber-100/55">
                Você precisa de pelo menos {{ $minInputs }} cópias do mesmo cosmético. Compre mais na loja.
            </div>
        @endforelse
    </section>

    <section class="mb-10 reveal space-y-4">
        <div>
            <h2 class="font-display text-2xl text-amber-200">Mascotes</h2>
            <p class="text-sm text-amber-100/55 mt-1">Selecione pelo menos {{ $minInputs }} cópias do mesmo mascote.</p>
        </div>

        @forelse($petGroups as $petId => $rows)
            @php $sample = $rows->first(); @endphp
            <article class="game-card p-5 space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="font-display text-xl text-amber-100">{{ $sample['species_name'] }}</p>
                        <p class="text-sm text-amber-100/55">
                            {{ $sample['rarity_label'] }} · {{ $rows->count() }} cópia(s)
                        </p>
                    </div>
                    <p class="text-sm text-cyan-300" data-forge-preview>Selecione itens para ver o resultado</p>
                </div>
                <form method="POST" action="{{ route('student.forge.pets') }}" class="space-y-4" data-forge-form data-unit-price="1">
                    @csrf
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($rows as $row)
                            <label class="game-card p-3 flex gap-3 cursor-pointer border border-amber-500/10 hover:border-amber-400/40">
                                <input
                                    type="checkbox"
                                    name="pet_ids[]"
                                    value="{{ $row['id'] }}"
                                    class="mt-1"
                                    data-forge-input
                                    data-level="{{ $row['forge_level'] }}"
                                    data-bonus="{{ $row['combat_bonus'] }}"
                                    @disabled($row['listed_price'] !== null)
                                >
                                <span class="min-w-0">
                                    <span class="block font-display text-amber-100">{{ $row['custom_name'] }}</span>
                                    <span class="block text-xs text-orange-300/80 mt-1">{{ $row['forge_level_label'] }} · +{{ $row['combat_bonus_percent'] }}% · {{ $row['aura_label'] }}</span>
                                    @if($row['listed_price'] !== null)
                                        <span class="block text-xs text-rose-300 mt-1">Anunciado — retire antes de forjar</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <button type="submit" class="game-btn" data-forge-submit disabled>Forjar selecionados</button>
                </form>
            </article>
        @empty
            <div class="game-card p-5 text-amber-100/55">
                Você precisa de pelo menos {{ $minInputs }} cópias do mesmo mascote. Compre mais na loja.
            </div>
        @endforelse
    </section>

    @if($vault)
        <section class="game-card p-5 reveal">
            <h2 class="font-display text-xl text-amber-200">Cofre da forja (reino)</h2>
            <p class="text-sm text-amber-100/60 mt-2">
                <span class="text-cyan-300">{{ \App\Models\GameCurrency::format('relics', $vault->relics) }}</span>
                ·
                <span class="text-emerald-300">{{ \App\Models\GameCurrency::format('seals', $vault->seals) }}</span>
                ·
                <span class="text-violet-300">{{ \App\Models\GameCurrency::format('auras', $vault->auras) }}</span>
            </p>
            <p class="text-xs text-amber-100/45 mt-2">O admin sorteia este pote entre quem forjou desde o último sorteio.</p>
        </section>
    @endif
@endunless

@push('scripts')
<script>
(() => {
    const feeRate = {{ json_encode($feeRate) }};
    const minInputs = {{ (int) $minInputs }};

    document.querySelectorAll('[data-forge-form]').forEach((form) => {
        const preview = form.closest('article')?.querySelector('[data-forge-preview]');
        const submit = form.querySelector('[data-forge-submit]');
        const unitPrice = Number(form.dataset.unitPrice || 1);

        const refresh = () => {
            const checked = [...form.querySelectorAll('[data-forge-input]:checked')];
            if (submit) {
                submit.disabled = checked.length < minInputs;
            }
            if (!preview) {
                return;
            }
            if (checked.length < minInputs) {
                preview.textContent = 'Selecione itens para ver o resultado';
                return;
            }
            const level = checked.reduce((sum, el) => sum + Number(el.dataset.level || 1), 0);
            const bonus = checked.reduce((sum, el) => sum + Number(el.dataset.bonus || 0), 0);
            const fee = Math.max(1, Math.ceil(unitPrice * checked.length * feeRate));
            preview.textContent = `Resultado: Nível ${level} · +${(bonus * 100).toFixed(2)}% · taxa ${fee}`;
        };

        form.addEventListener('change', refresh);
        refresh();
    });
})();
</script>
@endpush
@endsection
