<?php

namespace App\Http\Controllers;

use App\Models\Area;
use Illuminate\View\View;

class AreaController extends Controller
{
    public function show(Area $area): View
    {
        abort_unless($area->is_active, 404);

        $area->load(['classes' => fn ($q) => $q->orderBy('name')]);

        return view('areas.show', compact('area'));
    }
}
