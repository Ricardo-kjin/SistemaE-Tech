<?php

namespace Tests\Concerns;

trait PortadaBcb
{
    /**
     * Portada real de www.bcb.gob.bo descargada el 22/10/2026 (tipo de cambio oficial 11,75 con vigencia 2026-10-22).
     * Los parámetros permiten alterar solo la tarjeta "Tipo de cambio oficial".
     */
    protected function portadaBcb(?string $valor = null, ?string $fecha = null, ?string $subtitulo = null): string
    {
        $html = file_get_contents(__DIR__.'/../Fixtures/bcb_portada.html');

        $inicio = strpos($html, '<article class="bcb-kpi2-card is-tc-oficial">');
        $fin = strpos($html, '</article>', $inicio) + strlen('</article>');
        $tarjeta = substr($html, $inicio, $fin - $inicio);
        $original = $tarjeta;

        if ($valor !== null) {
            $tarjeta = str_replace('<span class="bcb-tco-num">11,75</span>', '<span class="bcb-tco-num">'.$valor.'</span>', $tarjeta);
        }

        if ($fecha !== null) {
            $tarjeta = str_replace('<time datetime="2026-10-22">', '<time datetime="'.$fecha.'">', $tarjeta);
        }

        if ($subtitulo !== null) {
            $tarjeta = str_replace('Bolivianos por dólar estadounidense', $subtitulo, $tarjeta);
        }

        return str_replace($original, $tarjeta, $html);
    }

    protected function portadaSinTarjeta(): string
    {
        return str_replace('is-tc-oficial', 'is-otra-tarjeta', $this->portadaBcb());
    }
}
