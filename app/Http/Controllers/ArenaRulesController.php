<?php

namespace App\Http\Controllers;

use App\Models\Duel;
use App\Models\User;
use Illuminate\View\View;

class ArenaRulesController extends Controller
{
    public function show(): View
    {
        return view('arena.rules', [
            'roles' => User::characterClassesGroupedByRole(),
            'gloryWin' => Duel::GLORY_WIN,
            'gloryLoss' => Duel::GLORY_LOSS,
        ]);
    }
}
