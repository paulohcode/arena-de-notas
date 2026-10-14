<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Services\ForgeService;
use App\Support\ForgeCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ForgeController extends Controller
{
    public function __construct(private ForgeService $forge) {}

    public function index(Request $request): View|RedirectResponse
    {
        $class = $this->currentClass($request);
        if (! $class) {
            return view('student.empty');
        }

        $student = $request->user();
        $this->authorize('viewAsStudent', $class);

        $data = $this->forge->forgeData($student, $class);

        return view('student.forge', [
            'class' => $class,
            'student' => $student,
            'available' => $data['available'],
            'cosmetics' => $data['cosmetics'],
            'pets' => $data['pets'],
            'vault' => $data['vault'],
            'feeRate' => ForgeCatalog::FEE_RATE,
            'minInputs' => ForgeCatalog::MIN_INPUTS,
        ]);
    }

    public function forgeCosmetic(Request $request): RedirectResponse
    {
        $class = $this->currentClass($request);
        abort_unless($class, 404);
        $this->authorize('viewAsStudent', $class);

        $data = $request->validate([
            'cosmetic_ids' => ['required', 'array', 'min:'.ForgeCatalog::MIN_INPUTS],
            'cosmetic_ids.*' => ['integer', 'min:1'],
        ]);

        try {
            $result = $this->forge->forgeCosmetics($request->user(), $class, $data['cosmetic_ids']);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('student.forge.index')
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('student.forge.index')
            ->with(
                'success',
                'Forja concluída: '.$result->toInventoryArray()['name']
                .' '.$result->forgeLevelLabel()
                .' (+'.round($result->effectiveCombatBonus() * 100, 2).'%).',
            );
    }

    public function forgePet(Request $request): RedirectResponse
    {
        $class = $this->currentClass($request);
        abort_unless($class, 404);
        $this->authorize('viewAsStudent', $class);

        $data = $request->validate([
            'pet_ids' => ['required', 'array', 'min:'.ForgeCatalog::MIN_INPUTS],
            'pet_ids.*' => ['integer', 'min:1'],
        ]);

        try {
            $result = $this->forge->forgePets($request->user(), $class, $data['pet_ids']);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('student.forge.index')
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('student.forge.index')
            ->with(
                'success',
                'Forja concluída: '.$result->custom_name
                .' '.$result->forgeLevelLabel()
                .' (+'.round($result->effectiveCombatBonus() * 100, 2).'%).',
            );
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
