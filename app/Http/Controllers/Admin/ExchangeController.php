<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Models\GameCurrency;
use App\Services\CurrencyExchangeService;
use App\Support\ExchangeCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExchangeController extends Controller
{
    public function __construct(private CurrencyExchangeService $exchange) {}

    public function index(): View
    {
        return view('admin.exchange.index', [
            'rates' => $this->exchange->ratesForAdmin(),
            'currencyOptions' => GameCurrency::shopLabels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = validator(
            $request->all(),
            ExchangeCatalog::storeRules(),
            ExchangeCatalog::messages(),
        );
        $validator->after(ExchangeCatalog::uniqueOfferAfter());
        $data = $validator->validate();

        $rate = $this->exchange->createRate($data);

        return redirect()
            ->route('admin.exchange.index')
            ->with('success', 'Oferta cadastrada: '.$rate->offerLabel().'.');
    }

    public function edit(ExchangeRate $exchangeRate): View
    {
        return view('admin.exchange.edit', [
            'rate' => $exchangeRate,
            'currencyOptions' => GameCurrency::shopLabels(),
        ]);
    }

    public function update(Request $request, ExchangeRate $exchangeRate): RedirectResponse
    {
        $validator = validator(
            $request->all(),
            ExchangeCatalog::updateRules(),
            ExchangeCatalog::messages(),
        );
        $validator->after(ExchangeCatalog::uniqueOfferAfter($exchangeRate));
        $data = $validator->validate();
        $data['is_active'] = $request->boolean('is_active');

        $rate = $this->exchange->updateRate($exchangeRate, $data);

        return redirect()
            ->route('admin.exchange.index')
            ->with('success', 'Oferta atualizada: '.$rate->offerLabel().'.');
    }

    public function destroy(ExchangeRate $exchangeRate): RedirectResponse
    {
        $label = $exchangeRate->offerLabel();
        $this->exchange->deleteRate($exchangeRate);

        return redirect()
            ->route('admin.exchange.index')
            ->with('success', 'Oferta removida: '.$label.'.');
    }
}
