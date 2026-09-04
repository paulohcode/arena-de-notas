<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Season;
use App\Services\SeasonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SeasonRankingController extends Controller
{
    public function __construct(private SeasonService $seasons) {}

    public function indexRedirect(): RedirectResponse
    {
        return redirect()->route('home');
    }

    public function index(Area $area): View
    {
        abort_unless($area->is_active, 404);

        $seasons = Season::query()
            ->where('area_id', $area->id)
            ->withCount('classes')
            ->latest()
            ->get();

        return view('seasons.index', compact('area', 'seasons'));
    }

    public function show(Area $area, Season $season): View
    {
        abort_unless($area->is_active, 404);
        abort_unless($season->area_id === $area->id, 404);

        $ranking = $this->seasons->classRanking($season);

        return view('seasons.show', compact('area', 'season', 'ranking'));
    }
}
