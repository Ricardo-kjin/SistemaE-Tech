<?php

namespace App\Services\TipoCambio;

use Carbon\CarbonImmutable;

final class ResultadoActualizacion
{
    public const CREADO = 'creado';
    public const ACTUALIZADO = 'actualizado';
    public const SIN_CAMBIOS = 'sin_cambios';

    public function __construct(
        public readonly CotizacionBcb $cotizacion,
        public readonly string $estado,
    ) {
    }

    public function mensaje(): string
    {
        $fecha = CarbonImmutable::createFromFormat('!Y-m-d', $this->cotizacion->fecha)->format('d/m/Y');
        $dato = "{$this->cotizacion->moneda} {$this->cotizacion->valor} con vigencia {$fecha}";

        return match ($this->estado) {
            self::CREADO => "Tipo de cambio registrado: {$dato}.",
            self::ACTUALIZADO => "Tipo de cambio actualizado: {$dato}.",
            default => "El tipo de cambio ya estaba registrado: {$dato}. No hubo cambios.",
        };
    }
}
