<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\GameCurrency;
use App\Services\ForgeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ForgeController extends Controller
{
    public function __construct(private ForgeService $forge) {}

    public function index(): View
    {
        return view('admin.forge.index', [
            'vaults' => $this->forge->vaultsForAdmin(),
        ]);
    }

    public function raffle(Request $request, Area $area): RedirectResponse
    {
        try {
            $raffle = $this->forge->raffle($area, $request->user());
        } catch (ValidationException $exception) {
            return redirect()
                ->route('admin.forge.index')
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
            ->route('admin.forge.index')
            ->with(
                'success',
                'Sorteio da forja em '.$area->name.': '
                .($raffle->winner?->name ?? 'aluno')
                .' ganhou '
                .implode(' + ', $parts)
                .'.',
            );
    }
}
