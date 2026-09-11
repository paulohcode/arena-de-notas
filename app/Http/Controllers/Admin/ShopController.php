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
                ->with(['schoolClass', 'area'])
                ->orderByDesc('id')
                ->get(),
            'slots' => CosmeticCatalog::SLOTS,
            'rarities' => CosmeticCatalog::RARITIES,
            'currencies' => CosmeticCatalog::currencies(),
            'cssTones' => CosmeticCatalog::CSS_TONES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(CosmeticCatalog::itemRules(), CosmeticCatalog::itemMessages());
        $item = $this->shop->createItem($data);
        $success = $item->usesAuras()
            ? $item->name.' criado: único em cada reino.'
            : $item->name.' criado e disponível em todas as turmas.';

        return redirect()
            ->route('admin.shop.index')
            ->with('success', $success);
    }

    public function edit(ShopItem $shopItem): View
    {
        return view('admin.shop.edit', [
            'item' => $shopItem,
            'slots' => CosmeticCatalog::SLOTS,
            'rarities' => CosmeticCatalog::RARITIES,
            'currencies' => CosmeticCatalog::currencies(),
            'cssTones' => CosmeticCatalog::CSS_TONES,
        ]);
    }

    public function update(Request $request, ShopItem $shopItem): RedirectResponse
    {
        $data = $request->validate(CosmeticCatalog::itemUpdateRules(), CosmeticCatalog::itemMessages());
        $item = $this->shop->updateItem($shopItem, $data);

        return redirect()
            ->route('admin.shop.index')
            ->with('success', $item->name.' atualizado.');
    }
}
