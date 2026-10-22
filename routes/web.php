<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TipoCambioController;
use App\Models\TipoCambio;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', function () {
    $ultimo = null;

    try {
        $ultimo = TipoCambio::query()->orderByDesc('FECHA')->first();
    } catch (\Illuminate\Database\QueryException) {
        // El dashboard no debe caerse si SQL Server no está disponible.
    }

    return view('dashboard', compact('ultimo'));
})->middleware(['auth', 'permission:ver dashboard'])->name('dashboard');

Route::middleware(['auth', 'permission:ver tipo cambio'])
    ->prefix('administracion/tipo-cambio')
    ->name('tipo-cambio.')
    ->group(function () {
        Route::get('/', [TipoCambioController::class, 'index'])->name('index');
        Route::post('/actualizar', [TipoCambioController::class, 'actualizar'])
            ->middleware(['permission:actualizar tipo cambio', 'throttle:6,1'])
            ->name('actualizar');
    });

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
