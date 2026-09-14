<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ArenaController extends Controller
{
    public function index(): RedirectResponse|View
    {
        $areas = Area::query()
            ->withCount('classes')
            ->orderBy('name')
            ->get();

        if ($areas->count() === 1) {
            return redirect()->route('admin.areas.arena', $areas->first());
        }

        return view('admin.arena.index', [
            'areas' => $areas,
        ]);
    }
}
