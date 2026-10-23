<?php

namespace App\Http\Controllers;

use App\Models\TipoCambio;
use App\Services\TipoCambio\BcbTipoCambioService;
use App\Services\TipoCambio\TipoCambioBcbException;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class TipoCambioController extends Controller
{
    public function index(): View
    {
        try {
            $registros = TipoCambio::query()
                ->orderByDesc('FECHA')
                ->paginate(15);
        } catch (QueryException $e) {
            $registros = new LengthAwarePaginator([], 0, 15, 1, [
                'path' => request()->url(),
            ]);

            return view('tipo-cambio.index', compact('registros'))
                ->with('error', 'No se pudo consultar CAMBIOS.dbo.CAMBIOS: '.$e->getMessage());
        }

        return view('tipo-cambio.index', compact('registros'));
    }

    public function actualizar(BcbTipoCambioService $servicio): RedirectResponse
    {
        try {
            $mensaje = $servicio->actualizar('manual:web')->mensaje();

            return redirect()->route('tipo-cambio.index')->with('success', $mensaje);
        } catch (TipoCambioBcbException $e) {
            return redirect()->route('tipo-cambio.index')->with('error', $e->getMessage());
        }
    }
}
