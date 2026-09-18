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
            'declineGlory' => Duel::DECLINE_PENALTY_GLORY,
            'declineRelics' => Duel::DECLINE_PENALTY_RELICS,
            'weeklyQuotaDefault' => Duel::WEEKLY_QUOTA_DEFAULT,
            'weeklyQuotaPenalty' => Duel::WEEKLY_QUOTA_PENALTY,
        ]);
    }
}
