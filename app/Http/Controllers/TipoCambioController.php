<?php

namespace App\Http\Controllers;

use App\Models\TipoCambio;
use App\Services\TipoCambio\BcbTipoCambioService;
use App\Services\TipoCambio\TipoCambioBcbException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TipoCambioController extends Controller
{
    public function index(): View
    {
        $registros = TipoCambio::query()
            ->orderByDesc('fecha')
            ->orderBy('moneda')
            ->paginate(15);

        return view('tipo-cambio.index', compact('registros'));
    }

    public function actualizar(BcbTipoCambioService $servicio): RedirectResponse
    {
        try {
            $mensaje = $servicio->actualizar('manual:'.auth()->user()->email)->mensaje();

            return redirect()->route('tipo-cambio.index')->with('success', $mensaje);
        } catch (TipoCambioBcbException $e) {
            return redirect()->route('tipo-cambio.index')->with('error', $e->getMessage());
        }
    }
}
