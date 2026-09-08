<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Services\SeasonService;
use Illuminate\View\View;

class AreaController extends Controller
{
    public function __construct(private SeasonService $seasons) {}

    public function show(Area $area): View
    {
        abort_unless($area->is_active, 404);

        return view('areas.show', [
            'area' => $area,
            'ranking' => $this->seasons->areaClassRanking($area),
        ]);
    }
}
