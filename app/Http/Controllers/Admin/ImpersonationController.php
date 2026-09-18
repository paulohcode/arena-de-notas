<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImpersonationController extends Controller
{
    public function __construct(private ImpersonationService $impersonation) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'area' => ['nullable', 'integer', 'exists:areas,id'],
            'class' => ['nullable', 'integer', 'exists:classes,id'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $classesQuery = SchoolClass::query()
            ->with([
                'area:id,name,emblem',
                'students' => fn ($query) => $query->orderBy('name'),
            ])
            ->orderBy('name');

        if (isset($data['area'])) {
            $classesQuery->where('area_id', (int) $data['area']);
        }

        if (isset($data['class'])) {
            $classesQuery->where('id', (int) $data['class']);
        }

        $classes = $classesQuery->get();

        $search = isset($data['q']) ? mb_strtolower(trim($data['q'])) : '';
        if ($search !== '') {
            $classes = $classes
                ->map(function (SchoolClass $class) use ($search): SchoolClass {
                    $filtered = $class->students->filter(function (User $student) use ($search): bool {
                        $haystack = mb_strtolower(trim(implode(' ', array_filter([
                            $student->name,
                            $student->email,
                            $student->character_name,
                            $student->pending_character_name,
                        ]))));

                        return str_contains($haystack, $search);
                    })->values();

                    $class->setRelation('students', $filtered);

                    return $class;
                })
                ->filter(fn (SchoolClass $class): bool => $class->students->isNotEmpty())
                ->values();
        }

        return view('admin.impersonate.index', [
            'classes' => $classes,
            'areas' => Area::query()->orderBy('name')->get(['id', 'name']),
            'classOptions' => SchoolClass::query()
                ->with('area:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'area_id']),
            'selectedAreaId' => isset($data['area']) ? (int) $data['area'] : null,
            'selectedClassId' => isset($data['class']) ? (int) $data['class'] : null,
            'search' => $data['q'] ?? '',
            'returnUrl' => route('admin.impersonate.index', array_filter([
                'area' => $data['area'] ?? null,
                'class' => $data['class'] ?? null,
                'q' => $data['q'] ?? null,
            ], fn ($value) => $value !== null && $value !== '')),
        ]);
    }

    public function start(Request $request, SchoolClass $schoolClass, User $student): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($student->isStudent(), 404);
        abort_unless($schoolClass->students()->where('users.id', $student->id)->exists(), 404);

        $data = $request->validate([
            'return_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $this->impersonation->start(
            $request->user(),
            $student,
            $schoolClass,
            $data['return_url'] ?? null,
        );

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
