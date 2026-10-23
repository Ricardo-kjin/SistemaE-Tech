<?php

use App\Http\Controllers\TipoCambioController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TipoCambioController::class, 'index'])->name('tipo-cambio.index');
Route::get('/administracion/tipo-cambio', [TipoCambioController::class, 'index']);
Route::post('/actualizar', [TipoCambioController::class, 'actualizar'])
    ->middleware('throttle:6,1')
    ->name('tipo-cambio.actualizar');
