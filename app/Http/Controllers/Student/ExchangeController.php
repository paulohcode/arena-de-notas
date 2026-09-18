<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\GameCurrency;
use App\Models\SchoolClass;
use App\Services\CurrencyExchangeService;
use App\Support\ExchangeCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExchangeController extends Controller
{
    public function __construct(private CurrencyExchangeService $exchange) {}

    public function index(Request $request): View|RedirectResponse
    {
        $class = $this->currentClass($request);
        if (! $class) {
            return view('student.empty');
        }

        $student = $request->user();
        $this->authorize('viewAsStudent', $class);

        $data = $this->exchange->shopData($student, $class);

        return view('student.exchange', [
            'class' => $class,
            'student' => $student,
            'enrollment' => $data['enrollment'],
            'auras' => $data['auras'],
            'rates' => $data['rates'],
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
                (string) $data['direction'],
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
                'Troca concluída: '
                .GameCurrency::format($trade->pay_currency, $trade->pay_amount)
                .' → '
                .GameCurrency::format($trade->receive_currency, $trade->receive_amount)
                .'.',
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
