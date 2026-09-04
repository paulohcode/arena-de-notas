<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\CharacterPersonaService;
use App\Services\StudentSheetService;
use App\Support\ArenaUrl;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private StudentSheetService $sheet,
        private CharacterPersonaService $personas,
    ) {}

    public function editCharacter(Request $request): View
    {
        $student = $request->user();

        return view('student.character', [
            'classes' => User::CHARACTER_CLASSES,
            'avatars' => User::CHARACTER_AVATARS,
            'selected' => $student->character_class,
            'selectedAvatar' => $student->pending_character_avatar ?? $student->character_avatar,
            'characterName' => $student->pending_character_name ?? $student->character_name,
            'firstTime' => blank($student->character_class),
            'student' => $student,
        ]);
    }

    public function updateCharacter(Request $request): RedirectResponse
    {
        $student = $request->user();

        $data = $request->validate([
            'character_class' => ['required', Rule::in(array_keys(User::CHARACTER_CLASSES))],
            'character_name' => [
                'required',
                'string',
                'min:2',
                'max:24',
                'regex:/^[\p{L}0-9][\p{L}0-9 \-\']{0,22}$/u',
                function (string $attribute, mixed $value, Closure $fail) use ($student) {
                    if ($this->personas->nameIsTaken($student, (string) $value)) {
                        $fail('Este nome de personagem já está em uso.');
                    }
                },
            ],
            'character_avatar' => ['required', Rule::in(array_keys(User::CHARACTER_AVATARS))],
        ]);

        $this->personas->submit(
            $student,
            $data['character_class'],
            $data['character_name'],
            $data['character_avatar'],
        );

        return redirect()
            ->route('student.dashboard')
            ->with('success', 'Avatar e nome enviados. O professor precisa aprovar antes de aparecerem na arena.');
    }

    public function index(Request $request): View|RedirectResponse
    {
        $class = $this->currentClass($request);
        if (! $class) {
            return view('student.empty');
        }

        $student = $request->user();

        return view('student.dashboard', array_merge(
            $this->sheet->data($student, $class, publicPlayersOnly: true),
            [
                'viewerIsTeacher' => false,
                'classes' => $student->classes()->orderBy('name')->get(),
                'notifications' => $student->unreadNotifications()->latest()->limit(8)->get(),
                'notifyUrl' => ArenaUrl::route('student.notifications'),
                'markReadUrl' => ArenaUrl::route('student.notifications.read'),
                'rankingUrl' => ArenaUrl::route('ranking.live', $class),
            ],
        ));
    }

    public function privacy(Request $request): RedirectResponse
    {
        $class = $this->currentClass($request);
        abort_unless($class, 404);

        $enrollment = $request->user()->enrollmentIn($class);
        abort_unless($enrollment, 404);

        $enrollment->update([
            'ranking_visible' => $request->boolean('ranking_visible'),
        ]);

        return back()->with('success', 'Privacidade atualizada.');
    }

    public function notifications(Request $request): JsonResponse
    {
        $items = $request->user()->unreadNotifications()->latest()->limit(10)->get()->map(fn ($n) => [
            'id' => $n->id,
            'type' => $n->data['type'] ?? 'info',
            'title' => $n->data['title'] ?? '',
            'message' => $n->data['message'] ?? '',
            'payload' => ArenaUrl::localizePayload($n->data['payload'] ?? []),
            'created_at' => $n->created_at?->toIso8601String(),
        ]);

        return response()->json([
            'unread' => $request->user()->unreadNotifications()->count(),
            'items' => $items,
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['sometimes', 'array'],
            'ids.*' => ['required', 'uuid'],
        ]);

        $unread = $request->user()->unreadNotifications();

        if (array_key_exists('ids', $data)) {
            if ($data['ids'] === []) {
                return response()->json(['ok' => true]);
            }

            $unread->whereIn('id', $data['ids']);
        }

        $unread->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function switchClass(Request $request): RedirectResponse
    {
        $request->validate(['class_id' => ['required', 'exists:classes,id']]);
        $belongs = $request->user()->classes()->where('classes.id', $request->integer('class_id'))->exists();
        abort_unless($belongs, 403);

        $request->session()->put('current_class_id', $request->integer('class_id'));

        return back();
    }

    private function currentClass(Request $request): ?SchoolClass
    {
        $student = $request->user();
        $id = $request->session()->get('current_class_id');
        if ($id) {
            $class = $student->classes()->where('classes.id', $id)->first();
            if ($class) {
                return $class;
            }
        }

        return $student->classes()->orderBy('name')->first();
    }
}
