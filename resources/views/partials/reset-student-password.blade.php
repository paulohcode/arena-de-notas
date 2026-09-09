<form
    method="POST"
    action="{{ route('teacher.students.password', [$class, $student]) }}"
    onsubmit="return confirm(@js('Redefinir a senha de '.$student->name.' para '.\App\Services\ClassAccessPdfService::INITIAL_PASSWORD.'? No próximo acesso, o aluno precisará trocar.'))"
>
    @csrf
    <button class="{{ $buttonClass ?? 'game-btn-ghost !px-2 !py-1 text-xs' }}" type="submit">Resetar senha</button>
</form>
