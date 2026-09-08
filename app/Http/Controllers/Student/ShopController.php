<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Services\CosmeticShopService;
use App\Support\CosmeticCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function __construct(private CosmeticShopService $shop) {}

    public function index(Request $request): View|RedirectResponse
    {
        $class = $this->currentClass($request);
        if (! $class) {
            return view('student.empty');
        }

        $student = $request->user();
        $this->authorize('viewAsStudent', $class);

        $data = $this->shop->shopData($student, $class);

        return view('student.shop', [
            'class' => $class,
            'student' => $student,
            'enrollment' => $data['enrollment'],
            'catalog' => $data['catalog'],
            'loadout' => $data['loadout'],
            'slots' => CosmeticCatalog::SLOTS,
        ]);
    }

    public function purchase(Request $request): RedirectResponse
    {
        $class = $this->currentClass($request);
        abort_unless($class, 404);
        $this->authorize('viewAsStudent', $class);

        $data = $request->validate([
            'item' => ['required', 'string', Rule::in(array_keys(CosmeticCatalog::ITEMS))],
        ]);

        try {
            $this->shop->purchase($request->user(), $class, $data['item']);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('student.shop.index')
                ->withErrors($exception->errors());
        }

        $item = CosmeticCatalog::item($data['item']);

        return redirect()
            ->route('student.shop.index')
            ->with('success', ($item['name'] ?? 'Item').' adquirido! Equipe na loja quando quiser.');
    }

    public function equip(Request $request): RedirectResponse
    {
        $class = $this->currentClass($request);
        abort_unless($class, 404);
        $this->authorize('viewAsStudent', $class);

        $data = $request->validate([
            'item' => ['required', 'string', Rule::in(array_keys(CosmeticCatalog::ITEMS))],
        ]);

        try {
            $this->shop->equip($request->user(), $class, $data['item']);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('student.shop.index')
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('student.shop.index')
            ->with('success', 'Cosmético equipado!');
    }

    public function unequip(Request $request): RedirectResponse
    {
        $class = $this->currentClass($request);
        abort_unless($class, 404);
        $this->authorize('viewAsStudent', $class);

        $data = $request->validate([
            'slot' => ['required', 'string', Rule::in(array_keys(CosmeticCatalog::SLOTS))],
        ]);

        try {
            $this->shop->unequip($request->user(), $class, $data['slot']);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('student.shop.index')
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('student.shop.index')
            ->with('success', 'Cosmético removido.');
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
