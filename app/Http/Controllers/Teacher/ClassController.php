<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClassController extends Controller
{
    public function create(Request $request): View
    {
        return view('teacher.class-form', $this->formData($request, new SchoolClass([
            'score_mode' => 'up_from_zero',
            'team_grade_weight' => 1,
            'behavior_grade_weight' => 1,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['teacher_id'] = $request->user()->isAdmin()
            ? (int) $data['teacher_id']
            : $request->user()->id;

        $class = SchoolClass::create($data);

        return redirect()->route('teacher.classes.show', $class)->with('success', 'Turma criada.');
    }

    public function edit(Request $request, SchoolClass $schoolClass): View
    {
        $this->authorize('manage', $schoolClass);

        return view('teacher.class-form', $this->formData($request, $schoolClass));
    }

    public function update(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);

        $data = $this->validated($request);

        if ($request->user()->isAdmin()) {
            $data['teacher_id'] = (int) $data['teacher_id'];
        } else {
            unset($data['teacher_id']);
        }

        $schoolClass->update($data);

        return redirect()->route('teacher.classes.show', $schoolClass)->with('success', 'Turma atualizada.');
    }

    public function destroy(SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('manage', $schoolClass);
        $schoolClass->delete();

        return redirect()->route('teacher.dashboard')->with('success', 'Turma removida.');
    }

    /**
     * @return array{class: SchoolClass, areas: Collection, teachers: Collection|null, viewerIsAdmin: bool}
     */
    private function formData(Request $request, SchoolClass $class): array
    {
        $user = $request->user();
        $viewerIsAdmin = $user->isAdmin();

        return [
            'class' => $class,
            'areas' => $viewerIsAdmin
                ? Area::query()->orderBy('name')->get()
                : $user->areas()->orderBy('name')->get(),
            'teachers' => $viewerIsAdmin
                ? User::query()->where('role', 'teacher')->with('areas')->orderBy('name')->get()
                : null,
            'viewerIsAdmin' => $viewerIsAdmin,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $user = $request->user();
        $allowedAreaIds = $user->isAdmin()
            ? Area::query()->pluck('id')->all()
            : $user->areas()->pluck('areas.id')->all();

        $rules = [
            'area_id' => ['required', 'integer', Rule::in($allowedAreaIds)],
            'name' => ['required', 'string', 'max:120'],
            'year' => ['nullable', 'string', 'max:20'],
            'score_mode' => ['required', 'in:up_from_zero,down_from_hundred'],
            'team_grade_weight' => ['required', 'integer', 'min:1', 'max:10'],
            'behavior_grade_weight' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];

        if ($user->isAdmin()) {
            $rules['teacher_id'] = [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'teacher')),
            ];
        }

        $data = $request->validate($rules);

        if ($user->isAdmin()) {
            $teacher = User::query()->findOrFail((int) $data['teacher_id']);
            if (! $teacher->belongsToArea((int) $data['area_id'])) {
                throw ValidationException::withMessages([
                    'teacher_id' => 'O professor precisa estar vinculado ao reino da turma.',
                ]);
            }
        }

        return $data;
    }
}
