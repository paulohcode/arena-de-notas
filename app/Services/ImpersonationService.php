<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ImpersonationService
{
    public const SESSION_IMPERSONATOR_ID = 'impersonator_id';

    public const SESSION_RETURN_URL = 'impersonator_return_url';

    public function isActive(?Request $request = null): bool
    {
        $session = $request?->session() ?? session();

        return filled($session->get(self::SESSION_IMPERSONATOR_ID));
    }

    public function impersonatorId(?Request $request = null): ?int
    {
        $session = $request?->session() ?? session();
        $id = $session->get(self::SESSION_IMPERSONATOR_ID);

        return $id !== null ? (int) $id : null;
    }

    public function returnUrl(?Request $request = null): ?string
    {
        $session = $request?->session() ?? session();
        $url = $session->get(self::SESSION_RETURN_URL);

        return is_string($url) && $url !== '' ? $url : null;
    }

    public function start(User $admin, User $student, SchoolClass $class, ?string $returnUrl = null): void
    {
        if (! $admin->isAdmin()) {
            abort(403);
        }

        if ($this->isActive()) {
            throw ValidationException::withMessages([
                'impersonate' => 'Você já está vendo como outro aluno. Volte ao admin antes de trocar.',
            ]);
        }

        if (! $student->isStudent()) {
            abort(404);
        }

        abort_unless($class->students()->where('users.id', $student->id)->exists(), 404);

        Auth::login($student);

        session([
            self::SESSION_IMPERSONATOR_ID => $admin->id,
            self::SESSION_RETURN_URL => $this->safeReturnUrl($returnUrl) ?? route('admin.impersonate.index'),
            'current_class_id' => $class->id,
        ]);

        session()->regenerate();
    }

    public function stop(): User
    {
        $impersonatorId = $this->impersonatorId();

        if (! $impersonatorId) {
            throw ValidationException::withMessages([
                'impersonate' => 'Nenhuma visão de aluno está ativa.',
            ]);
        }

        $admin = User::query()->find($impersonatorId);

        if (! $admin || ! $admin->isAdmin()) {
            session()->forget([
                self::SESSION_IMPERSONATOR_ID,
                self::SESSION_RETURN_URL,
            ]);

            throw ValidationException::withMessages([
                'impersonate' => 'Não foi possível restaurar a conta de admin.',
            ]);
        }

        session()->forget([
            self::SESSION_IMPERSONATOR_ID,
            self::SESSION_RETURN_URL,
        ]);

        Auth::login($admin);
        session()->regenerate();

        return $admin;
    }

    public function allowsMutation(Request $request): bool
    {
        return $request->routeIs(
            'admin.impersonate.stop',
            'student.class.switch',
            'logout',
            'student.notifications.read',
        );
    }

    public function safeReturnUrl(?string $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $appUrl = rtrim((string) config('app.url'), '/');

        if ($appUrl !== '' && str_starts_with($url, $appUrl.'/')) {
            return $url;
        }

        return null;
    }
}
