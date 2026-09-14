<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function __construct(private ImpersonationService $impersonation) {}

    public function start(Request $request, SchoolClass $schoolClass, User $student): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($student->isStudent(), 404);
        abort_unless($schoolClass->students()->where('users.id', $student->id)->exists(), 404);

        $this->impersonation->start($request->user(), $student, $schoolClass);

        return redirect()
            ->route('student.dashboard')
            ->with('success', 'Você está vendo como '.$student->name.'. Alterações estão bloqueadas.');
    }

    public function stop(Request $request): RedirectResponse
    {
        $returnUrl = $this->impersonation->returnUrl($request);
        $this->impersonation->stop();

        return redirect()
            ->to($returnUrl ?? route('admin.dashboard'))
            ->with('success', 'Visão de admin restaurada.');
    }
}
