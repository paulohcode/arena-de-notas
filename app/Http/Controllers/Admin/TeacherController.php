<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class TeacherController extends Controller
{
    public function index(): View
    {
        $teachers = User::query()
            ->where('role', 'teacher')
            ->with('areas')
            ->orderBy('name')
            ->get();

        return view('admin.teachers.index', compact('teachers'));
    }

    public function create(): View
    {
        return view('admin.teachers.form', [
            'teacher' => new User(['role' => 'teacher', 'must_change_password' => true]),
            'areas' => Area::query()->orderBy('name')->get(),
            'selectedAreaIds' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $teacher = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'teacher',
            'must_change_password' => true,
        ]);

        $teacher->areas()->sync($data['area_ids'] ?? []);

        return redirect()->route('admin.teachers.index')
            ->with('success', "Professor \"{$teacher->name}\" cadastrado.");
    }

    public function edit(User $teacher): View
    {
        abort_unless($teacher->isTeacher(), 404);

        return view('admin.teachers.form', [
            'teacher' => $teacher,
            'areas' => Area::query()->orderBy('name')->get(),
            'selectedAreaIds' => $teacher->areas()->pluck('areas.id')->all(),
        ]);
    }

    public function update(Request $request, User $teacher): RedirectResponse
    {
        abort_unless($teacher->isTeacher(), 404);

        $data = $this->validated($request, $teacher);

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        if (filled($data['password'] ?? null)) {
            $payload['password'] = $data['password'];
            $payload['must_change_password'] = true;
        }

        $teacher->update($payload);

        $newAreaIds = collect($data['area_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
        $removedAreaIds = $teacher->areas()->pluck('areas.id')
            ->diff($newAreaIds)
            ->values()
            ->all();

        if ($removedAreaIds !== [] && $teacher->taughtClasses()->whereIn('area_id', $removedAreaIds)->exists()) {
            return back()
                ->withInput()
                ->withErrors([
                    'area_ids' => 'Este professor ainda tem turmas nos reinos removidos. Reatribua ou exclua essas turmas antes.',
                ]);
        }

        $teacher->areas()->sync($newAreaIds);

        return redirect()->route('admin.teachers.index')
            ->with('success', "Professor \"{$teacher->name}\" atualizado.");
    }

    public function destroy(User $teacher): RedirectResponse
    {
        abort_unless($teacher->isTeacher(), 404);

        if ($teacher->taughtClasses()->exists()) {
            return back()->withErrors(['teacher' => 'Remova ou transfira as turmas deste professor antes de excluí-lo.']);
        }

        $teacher->areas()->detach();
        $teacher->delete();

        return redirect()->route('admin.teachers.index')
            ->with('success', 'Professor removido.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $teacher = null): array
    {
        $passwordRules = $teacher
            ? ['nullable', 'confirmed', Password::min(6)]
            : ['required', 'confirmed', Password::min(6)];

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'email',
                'max:180',
                Rule::unique('users', 'email')->ignore($teacher?->id),
            ],
            'password' => $passwordRules,
            'area_ids' => ['nullable', 'array'],
            'area_ids.*' => ['integer', 'exists:areas,id'],
        ]);
    }
}
