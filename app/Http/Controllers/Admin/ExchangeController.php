<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\ExchangeRate;
use App\Models\GameCurrency;
use App\Services\CurrencyExchangeService;
use App\Services\PeerCurrencyTradeService;
use App\Support\ExchangeCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExchangeController extends Controller
{
    public function __construct(
        private CurrencyExchangeService $exchange,
        private PeerCurrencyTradeService $peerTrades,
    ) {}

    public function index(): View
    {
        return view('admin.exchange.index', [
            'rates' => $this->exchange->ratesForAdmin(),
            'currencyOptions' => GameCurrency::shopLabels(),
            'vaults' => $this->peerTrades->vaultsForAdmin(),
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
        $data = $request->validate(
            ExchangeCatalog::updateRules(),
            ExchangeCatalog::messages(),
        );
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

    public function raffle(Request $request, Area $area): RedirectResponse
    {
        try {
            $raffle = $this->peerTrades->raffle($area, $request->user());
        } catch (ValidationException $exception) {
            return redirect()
                ->route('admin.exchange.index')
                ->withErrors($exception->errors());
        }

        $parts = [];
        if ($raffle->relics > 0) {
            $parts[] = GameCurrency::format('relics', $raffle->relics);
        }
        if ($raffle->seals > 0) {
            $parts[] = GameCurrency::format('seals', $raffle->seals);
        }
        if ($raffle->auras > 0) {
            $parts[] = GameCurrency::format('auras', $raffle->auras);
        }

        return redirect()
            ->route('admin.exchange.index')
            ->with(
                'success',
                'Sorteio em '.$area->name.': '
                .($raffle->winner?->name ?? 'aluno')
                .' ganhou '
                .implode(' + ', $parts)
                .'.',
            );
    }
}
