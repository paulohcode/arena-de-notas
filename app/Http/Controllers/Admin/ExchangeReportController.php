<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Services\ExchangeReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ExchangeReportController extends Controller
{
    public function __construct(private ExchangeReportService $reports) {}

    public function show(Request $request): View
    {
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'area' => ['nullable', 'integer', 'exists:areas,id'],
        ]);

        $timezone = (string) config('app.display_timezone');
        $day = isset($data['date'])
            ? Carbon::createFromFormat('Y-m-d', $data['date'], $timezone)->startOfDay()
            : now($timezone)->startOfDay();

        $area = isset($data['area'])
            ? Area::query()->find((int) $data['area'])
            : null;

        $report = $this->reports->forDay($day, $area);

        return view('admin.reports.exchange', [
            'report' => $report,
            'areas' => Area::query()->orderBy('name')->get(['id', 'name']),
            'selectedDate' => $day->toDateString(),
            'selectedAreaId' => $area?->id,
        ]);
    }
}
