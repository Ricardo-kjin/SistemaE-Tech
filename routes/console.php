<?php

use App\Services\TipoCambio\BcbTipoCambioService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// El BCB publica a las 20:00 (hora de Bolivia) el tipo de cambio vigente; se consulta 5 minutos después.
Schedule::command('tipo-cambio:actualizar')
    ->dailyAt('20:05')
    ->timezone(BcbTipoCambioService::ZONA_HORARIA)
    ->withoutOverlapping(10)
    ->appendOutputTo(storage_path('logs/tipo_cambio_scheduler.log'));
