<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Services\DuelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ArenaController extends Controller
{
    public function __construct(private DuelService $duels) {}

    public function open(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);
        $this->duels->toggleArena($schoolClass, true);

        return redirect()
            ->route('teacher.classes.show', ['schoolClass' => $schoolClass, 'tab' => 'arena'])
            ->with('success', 'Arena aberta. Os alunos já podem se desafiar.');
    }

    public function close(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);
        $this->duels->toggleArena($schoolClass, false);

        return redirect()
            ->route('teacher.classes.show', ['schoolClass' => $schoolClass, 'tab' => 'arena'])
            ->with('success', 'Arena fechada.');
    }
}
