<?php

namespace App\Console\Commands;

use App\Services\TipoCambio\BcbTipoCambioService;
use App\Services\TipoCambio\TipoCambioBcbException;
use Illuminate\Console\Command;

class ActualizarTipoCambio extends Command
{
    protected $signature = 'tipo-cambio:actualizar';

    protected $description = 'Consulta el tipo de cambio oficial publicado por el BCB y lo registra en CAMBIOS.dbo.CAMBIOS';

    public function handle(BcbTipoCambioService $servicio): int
    {
        try {
            $this->info($servicio->actualizar('artisan')->mensaje());

            return self::SUCCESS;
        } catch (TipoCambioBcbException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
