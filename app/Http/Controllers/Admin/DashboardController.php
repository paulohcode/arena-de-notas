<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'areasCount' => Area::query()->count(),
            'teachersCount' => User::query()->where('role', 'teacher')->count(),
            'areas' => Area::query()->withCount(['teachers', 'classes'])->orderBy('name')->get(),
        ]);
    }
}
