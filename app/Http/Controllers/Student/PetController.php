<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\PetListing;
use App\Models\SchoolClass;
use App\Services\PetShopService;
use App\Support\PetCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PetController extends Controller
{
    public function __construct(private PetShopService $pets) {}

    public function index(Request $request): View|RedirectResponse
    {
        $class = $this->currentClass($request);
        if (! $class) {
            return view('student.empty');
        }

        $student = $request->user();
        $this->authorize('viewAsStudent', $class);

        $data = $this->pets->shopData($student, $class);

        return view('student.pets', [
            'class' => $class,
            'student' => $student,
            'enrollment' => $data['enrollment'],
            'catalog' => $data['catalog'],
            'owned' => $data['owned'],
            'equipped' => $data['equipped'],
            'listings' => $data['listings'],
            'relics' => $data['relics'],
            'seals' => $data['seals'],
            'auras' => $data['auras'],
            'auraColors' => PetCatalog::AURA_COLORS,
        ]);
    }

    public function purchase(Request $request): RedirectResponse
    {
        $class = $this->currentClass($request);
        abort_unless($class, 404);
        $this->authorize('viewAsStudent', $class);

        $data = $request->validate(
            PetCatalog::purchaseRules(),
            PetCatalog::purchaseMessages(),
        );

        try {
            $owned = $this->pets->purchase(
                $request->user(),
                $class,
                (int) $data['pet_id'],
                $data['custom_name'],
                $data['aura_color'],
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('student.pets.index')
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('student.pets.index')
            ->with('success', $owned->custom_name.' entrou para a sua coleção!');
    }

    public function list(Request $request): RedirectResponse
    {
        $class = $this->currentClass($request);
        abort_unless($class, 404);
        $this->authorize('viewAsStudent', $class);

        $data = $request->validate([
            'enrollment_pet_id' => ['required', 'integer', 'exists:enrollment_pets,id'],
            'price' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        try {
            $this->pets->listForSale(
                $request->user(),
                $class,
                (int) $data['enrollment_pet_id'],
                (int) $data['price'],
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('student.pets.index')
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('student.pets.index')
            ->with('success', 'Mascote anunciado no mercado.');
    }

    public function unlist(Request $request): RedirectResponse
    {
        $class = $this->currentClass($request);
        abort_unless($class, 404);
        $this->authorize('viewAsStudent', $class);

        $data = $request->validate([
            'enrollment_pet_id' => ['required', 'integer', 'exists:enrollment_pets,id'],
        ]);

        try {
            $this->pets->cancelListing($request->user(), $class, (int) $data['enrollment_pet_id']);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('student.pets.index')
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('student.pets.index')
            ->with('success', 'Anúncio removido.');
    }

    public function buyListing(Request $request, PetListing $listing): RedirectResponse
    {
        $class = $this->currentClass($request);
        abort_unless($class, 404);
        $this->authorize('viewAsStudent', $class);

        try {
            $owned = $this->pets->buyListing($request->user(), $class, $listing);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('student.pets.index')
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('student.pets.index')
            ->with('success', $owned->custom_name.' agora é seu!');
    }

    public function equip(Request $request): RedirectResponse
    {
        $class = $this->currentClass($request);
        abort_unless($class, 404);
        $this->authorize('viewAsStudent', $class);

        $data = $request->validate([
            'enrollment_pet_id' => ['required', 'integer', 'exists:enrollment_pets,id'],
        ]);

        try {
            $this->pets->equip($request->user(), $class, (int) $data['enrollment_pet_id']);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('student.pets.index')
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('student.pets.index')
            ->with('success', 'Mascote equipado!');
    }

    public function unequip(Request $request): RedirectResponse
    {
        $class = $this->currentClass($request);
        abort_unless($class, 404);
        $this->authorize('viewAsStudent', $class);

        try {
            $this->pets->unequip($request->user(), $class);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('student.pets.index')
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('student.pets.index')
            ->with('success', 'Mascote desequipado.');
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
