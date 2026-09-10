@php
    $prefix = $prefix ?? 'questions';
    $initialCount = (int) old('question_count', $initialCount ?? 3);
@endphp
<div
    x-data="quizQuestionBuilder({
        initialCount: {{ $initialCount }},
        oldQuestions: @js(old('questions', [])),
    })"
    class="space-y-4"
>
    <div class="flex flex-wrap items-end gap-3">
        <label class="space-y-1">
            <span class="text-xs uppercase tracking-wide text-purple-200/70">Quantidade de perguntas</span>
            <input class="game-input w-28" type="number" min="1" max="50" x-model.number="count" @change="syncCount()">
        </label>
        <p class="text-xs text-purple-200/60">Múltipla escolha (2–4 opções) ou Verdadeiro/Falso.</p>
    </div>

    <template x-for="(question, index) in questions" :key="index">
        <div class="rounded-xl border border-purple-800/50 bg-purple-950/30 p-4 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-display text-amber-200" x-text="'Pergunta ' + (index + 1)"></h3>
                <select class="game-select w-48" :name="'{{ $prefix }}[' + index + '][type]'" x-model="question.type" @change="onTypeChange(question)">
                    <option value="multiple_choice">Múltipla escolha</option>
                    <option value="true_false">Verdadeiro / Falso</option>
                </select>
            </div>
            <label class="block space-y-1">
                <span class="text-xs uppercase tracking-wide text-purple-200/70">Enunciado</span>
                <input class="game-input w-full" :name="'{{ $prefix }}[' + index + '][prompt]'" x-model="question.prompt" required maxlength="500">
            </label>

            <div class="space-y-2" x-show="question.type === 'multiple_choice'">
                <template x-for="(option, optIndex) in question.options" :key="optIndex">
                    <label class="flex items-center gap-2">
                        <input type="radio" :name="'{{ $prefix }}[' + index + '][correct_index]'" :value="optIndex" x-model.number="question.correct_index">
                        <input class="game-input flex-1" :name="'{{ $prefix }}[' + index + '][options][' + optIndex + ']'" x-model="question.options[optIndex]" :placeholder="'Alternativa ' + (optIndex + 1)" maxlength="200">
                    </label>
                </template>
                <div class="flex gap-2">
                    <button type="button" class="game-btn-ghost text-xs" @click="addOption(question)" x-show="question.options.length < 4">+ opção</button>
                    <button type="button" class="game-btn-ghost text-xs" @click="removeOption(question)" x-show="question.options.length > 2">− opção</button>
                </div>
            </div>

            <div class="space-y-2" x-show="question.type === 'true_false'" x-cloak>
                <label class="flex items-center gap-2">
                    <input type="radio" :name="'{{ $prefix }}[' + index + '][correct_index]'" value="0" x-model.number="question.correct_index">
                    <span>Verdadeiro</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" :name="'{{ $prefix }}[' + index + '][correct_index]'" value="1" x-model.number="question.correct_index">
                    <span>Falso</span>
                </label>
                <input type="hidden" :name="'{{ $prefix }}[' + index + '][options][0]'" value="Verdadeiro">
                <input type="hidden" :name="'{{ $prefix }}[' + index + '][options][1]'" value="Falso">
            </div>
        </div>
    </template>
</div>
