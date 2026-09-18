<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CurrencyTradeListing;
use App\Models\GameCurrency;
use App\Models\SchoolClass;
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

    public function index(Request $request): View|RedirectResponse
    {
        $class = $this->currentClass($request);
        if (! $class) {
            return view('student.empty');
        }

        $student = $request->user();
        $this->authorize('viewAsStudent', $class);

        $data = $this->exchange->shopData($student, $class);
        $market = $this->peerTrades->marketData($student, $class);

        return view('student.exchange', [
            'class' => $class,
            'student' => $student,
            'enrollment' => $data['enrollment'],
            'auras' => $data['auras'],
            'offers' => $data['offers'],
            'peerAvailable' => $market['available'],
            'openListings' => $market['open_listings'],
            'myListings' => $market['my_listings'],
            'currencyOptions' => GameCurrency::shopLabels(),
            'tab' => $request->string('tab')->toString() === 'negociar' ? 'negociar' : 'cambio',
        ]);
    }

    public function trade(Request $request): RedirectResponse
    {
        $class = $this->currentClass($request);
        abort_unless($class, 404);
        $this->authorize('viewAsStudent', $class);

        $data = $request->validate(
            ExchangeCatalog::tradeRules(),
            ExchangeCatalog::tradeMessages(),
        );

        try {
            $trade = $this->exchange->trade(
                $request->user(),
                $class,
                (int) $data['exchange_rate_id'],
                (int) $data['lots'],
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('student.exchange.index')
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('student.exchange.index')
            ->with(
                'success',
                'Compra concluída: '
                .GameCurrency::format($trade->receive_currency, $trade->receive_amount)
                .' por '
                .GameCurrency::format($trade->pay_currency, $trade->pay_amount)
                .'.',
            );
    }

    public function storeListing(Request $request): RedirectResponse
    {
        $class = $this->currentClass($request);
        abort_unless($class, 404);
        $this->authorize('viewAsStudent', $class);

        $data = $request->validate(
            ExchangeCatalog::listingRules(),
            ExchangeCatalog::listingMessages(),
        );

        try {
            $listing = $this->peerTrades->createListing(
                $request->user(),
                $class,
                (string) $data['offer_currency'],
                (int) $data['offer_amount'],
                (string) $data['ask_currency'],
                (int) $data['ask_amount'],
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('student.exchange.index', ['tab' => 'negociar'])
                ->withErrors($exception->errors())
                ->withInput();
        }

        $fee = $listing->feeAmount();

        return redirect()
            ->route('student.exchange.index', ['tab' => 'negociar'])
            ->with(
                'success',
                'Anúncio criado: oferece '
                .$listing->offerLabel()
                .' por '
                .$listing->askLabel()
                .' (comprador pagará taxa de '
                .GameCurrency::format($listing->ask_currency, $fee)
                .').',
            );
    }

    public function cancelListing(Request $request, CurrencyTradeListing $listing): RedirectResponse
    {
        $class = $this->currentClass($request);
        abort_unless($class, 404);
        $this->authorize('viewAsStudent', $class);

        try {
            $this->peerTrades->cancelListing($request->user(), $class, $listing);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('student.exchange.index', ['tab' => 'negociar'])
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('student.exchange.index', ['tab' => 'negociar'])
            ->with('success', 'Anúncio cancelado. As moedas oferecidas voltaram para você.');
    }

    public function acceptListing(Request $request, CurrencyTradeListing $listing): RedirectResponse
    {
        $class = $this->currentClass($request);
        abort_unless($class, 404);
        $this->authorize('viewAsStudent', $class);

        try {
            $trade = $this->peerTrades->acceptListing($request->user(), $class, $listing);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('student.exchange.index', ['tab' => 'negociar'])
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('student.exchange.index', ['tab' => 'negociar'])
            ->with(
                'success',
                'Negociação concluída: você recebeu '
                .GameCurrency::format($trade->offer_currency, $trade->offer_amount)
                .' e pagou '
                .GameCurrency::format($trade->ask_currency, $trade->ask_amount + $trade->fee_amount)
                .' (taxa '
                .GameCurrency::format($trade->fee_currency, $trade->fee_amount)
                .' para o cofre).',
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
