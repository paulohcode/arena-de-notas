<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\ShopItem;
use App\Services\CosmeticShopService;
use App\Support\CosmeticCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function __construct(private CosmeticShopService $shop) {}

    public function index(): View
    {
        $classes = SchoolClass::query()
            ->with(['area', 'teacher'])
            ->withCount('students')
            ->orderBy('name')
            ->get();

        return view('admin.shop.index', [
            'classes' => $classes,
            'overview' => $this->shop->overviewForClasses($classes),
            'customItems' => ShopItem::query()
                ->with('schoolClass')
                ->orderByDesc('id')
                ->get(),
            'slots' => CosmeticCatalog::SLOTS,
            'rarities' => CosmeticCatalog::RARITIES,
            'currencies' => CosmeticCatalog::CURRENCIES,
            'cssTones' => CosmeticCatalog::CSS_TONES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(CosmeticCatalog::itemRules(), CosmeticCatalog::itemMessages());
        $item = $this->shop->createItem($data);

        return redirect()
            ->route('admin.shop.index')
            ->with('success', $item->name.' criado e disponível em todas as turmas.');
    }
}
