<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameCurrency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CurrencyController extends Controller
{
    public function index(): View
    {
        return view('admin.currencies.index', [
            'currencies' => GameCurrency::ordered(),
            'iconSuggestions' => GameCurrency::ICON_SUGGESTIONS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(GameCurrency::updateRules(), GameCurrency::updateMessages());
        GameCurrency::syncCatalog($data['currencies']);

        return redirect()
            ->route('admin.currencies.index')
            ->with('success', 'Nomes e ícones das moedas atualizados.');
    }
}
