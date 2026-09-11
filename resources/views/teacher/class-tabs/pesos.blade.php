    <div>
        <form method="POST" action="{{ route('teacher.activities.weights', $class) }}" class="game-card p-5 space-y-3">
            @csrf @method('PUT')
            @foreach($class->activities as $activity)
                <label class="flex items-center justify-between gap-4">
                    <span>{{ $activity->name }} <span class="text-purple-300/60">({{ $activity->type === 'team' ? 'fecha a nota da guilda' : 'individual' }})</span></span>
                    <input class="game-input w-24" type="number" name="weights[{{ $activity->id }}]" value="{{ $activity->weight }}" min="1" max="10">
                </label>
            @endforeach
            <label class="flex items-center justify-between gap-4">
                <span>Peso da linha “Nota equipe” no aluno</span>
                <input class="game-input w-24" type="number" name="team_grade_weight" value="{{ $class->team_grade_weight }}" min="1" max="10">
            </label>
            <label class="flex items-center justify-between gap-4">
                <span>Peso da nota “Comportamento”</span>
                <input class="game-input w-24" type="number" name="behavior_grade_weight" value="{{ $class->behavior_grade_weight ?? 1 }}" min="1" max="10">
            </label>
            <label class="flex items-center justify-between gap-4">
                <span>Peso da nota “Frequência”</span>
                <input class="game-input w-24" type="number" name="attendance_grade_weight" value="{{ $class->attendance_grade_weight ?? 1 }}" min="1" max="10">
            </label>
            <button class="game-btn" type="submit">Salvar pesos</button>
        </form>
    </div>
