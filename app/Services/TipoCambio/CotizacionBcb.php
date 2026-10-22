<?php

namespace App\Services\TipoCambio;

final class CotizacionBcb
{
    /**
     * @param  string  $fecha  Fecha de vigencia publicada (Y-m-d).
     * @param  string  $valor  Valor decimal normalizado con punto, como cadena para no perder precisión.
     */
    public function __construct(
        public readonly string $fecha,
        public readonly string $moneda,
        public readonly string $valor,
    ) {
    }
}
