<?php

namespace App\Services\TipoCambio;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Obtiene el tipo de cambio oficial publicado en la portada del Banco Central de Bolivia
 * (tarjeta "Tipo de cambio oficial", bolivianos por dólar estadounidense) y lo registra
 * en `tipo_cambio` de forma idempotente por (fecha, moneda).
 */
class BcbTipoCambioService
{
    public const FUENTE = 'BCB - Tipo de cambio oficial (www.bcb.gob.bo)';
    public const ZONA_HORARIA = 'America/La_Paz';

    private const LOCK = 'tipo-cambio:bcb:actualizar';
    private const LOCK_SEGUNDOS = 120;

    /** Monedas que la tarjeta oficial puede declarar en su subtítulo, con su código ISO 4217. */
    private const MONEDAS = [
        'dolar estadounidense' => 'USD',
    ];

    /**
     * Consulta el BCB y guarda el dato. Es el mismo proceso para el botón y para el Scheduler.
     *
     * @throws TipoCambioBcbException
     */
    public function actualizar(string $origen = 'manual'): ResultadoActualizacion
    {
        $lock = Cache::lock(self::LOCK, self::LOCK_SEGUNDOS);

        if (! $lock->get()) {
            $this->log()->warning('Actualización omitida: ya hay otra en curso.', ['origen' => $origen]);

            throw new TipoCambioBcbException('Ya hay una actualización del tipo de cambio en curso. Intente nuevamente en unos minutos.');
        }

        try {
            $cotizacion = $this->extraer($this->descargar());
            $resultado = new ResultadoActualizacion($cotizacion, $this->guardar($cotizacion));

            $this->log()->info('Actualización completada.', [
                'origen' => $origen,
                'fecha' => $cotizacion->fecha,
                'moneda' => $cotizacion->moneda,
                'valor' => $cotizacion->valor,
                'estado' => $resultado->estado,
            ]);

            return $resultado;
        } catch (TipoCambioBcbException $e) {
            $this->log()->error('Actualización fallida: '.$e->getMessage(), ['origen' => $origen]);

            throw $e;
        } catch (Throwable $e) {
            $this->log()->error('Error inesperado al actualizar el tipo de cambio.', [
                'origen' => $origen,
                'excepcion' => $e::class,
                'mensaje' => $e->getMessage(),
            ]);

            throw new TipoCambioBcbException('Error inesperado al actualizar el tipo de cambio. Revise storage/logs/tipo_cambio-AAAA-MM-DD.log.', 0, $e);
        } finally {
            $lock->release();
        }
    }

    /**
     * @throws TipoCambioBcbException
     */
    public function descargar(): string
    {
        $url = config('services.bcb.url');

        try {
            $respuesta = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; '.config('app.name').')',
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'es-BO,es;q=0.9',
            ])
                ->connectTimeout((int) config('services.bcb.connect_timeout'))
                ->timeout((int) config('services.bcb.timeout'))
                ->retry(2, 2000, fn (Throwable $e) => $e instanceof ConnectionException, throw: false)
                ->get($url);
        } catch (ConnectionException $e) {
            throw new TipoCambioBcbException('No se pudo conectar con el BCB ('.$url.'): '.$e->getMessage(), 0, $e);
        }

        if (! $respuesta->successful()) {
            throw new TipoCambioBcbException("El BCB respondió con el estado HTTP {$respuesta->status()}. No se modificaron los datos.");
        }

        $html = $respuesta->body();

        if (trim($html) === '') {
            throw new TipoCambioBcbException('El BCB devolvió una respuesta vacía. No se modificaron los datos.');
        }

        return $html;
    }

    /**
     * Extrae y valida la cotización desde el HTML de la portada del BCB.
     *
     * @throws TipoCambioBcbException
     */
    public function extraer(string $html): CotizacionBcb
    {
        $xpath = $this->xpath($html);

        $tarjetas = $xpath->query("//article[contains(concat(' ', normalize-space(@class), ' '), ' is-tc-oficial ')]");

        if ($tarjetas === false || $tarjetas->length !== 1) {
            throw new TipoCambioBcbException('No se encontró la tarjeta "Tipo de cambio oficial" en la portada del BCB. Es posible que la página haya cambiado de estructura.');
        }

        /** @var DOMElement $tarjeta */
        $tarjeta = $tarjetas->item(0);

        return new CotizacionBcb(
            fecha: $this->extraerFecha($xpath, $tarjeta),
            moneda: $this->extraerMoneda($xpath, $tarjeta),
            valor: $this->extraerValor($xpath, $tarjeta),
        );
    }

    /**
     * Inserta o actualiza en una sola sentencia atómica usando la restricción única (fecha, moneda).
     * Solo modifica la fila existente si el valor o la fuente cambiaron.
     */
    public function guardar(CotizacionBcb $cotizacion): string
    {
        $fila = DB::selectOne(<<<'SQL'
            INSERT INTO tipo_cambio (fecha, moneda, tipo_cambio, fuente)
            VALUES (?, ?, ?, ?)
            ON CONFLICT (fecha, moneda) DO UPDATE
                SET tipo_cambio = EXCLUDED.tipo_cambio,
                    fuente = EXCLUDED.fuente,
                    fecha_registro = CURRENT_TIMESTAMP
                WHERE tipo_cambio.tipo_cambio IS DISTINCT FROM EXCLUDED.tipo_cambio
                   OR tipo_cambio.fuente IS DISTINCT FROM EXCLUDED.fuente
            RETURNING (xmax = 0) AS insertado
        SQL, [$cotizacion->fecha, $cotizacion->moneda, $cotizacion->valor, self::FUENTE]);

        if ($fila === null) {
            return ResultadoActualizacion::SIN_CAMBIOS;
        }

        return $fila->insertado ? ResultadoActualizacion::CREADO : ResultadoActualizacion::ACTUALIZADO;
    }

    private function extraerFecha(DOMXPath $xpath, DOMElement $tarjeta): string
    {
        $nodo = $xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' bcb-kpi2-asof ')]//time[@datetime]", $tarjeta)->item(0);

        if (! $nodo instanceof DOMElement) {
            throw new TipoCambioBcbException('La tarjeta del BCB no publica la fecha de vigencia. No se modificaron los datos.');
        }

        $texto = trim($nodo->getAttribute('datetime'));

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto)) {
            throw new TipoCambioBcbException("La fecha de vigencia publicada por el BCB tiene un formato inesperado: \"{$texto}\".");
        }

        $fecha = CarbonImmutable::createFromFormat('!Y-m-d', $texto, self::ZONA_HORARIA);

        if ($fecha === null || $fecha->format('Y-m-d') !== $texto) {
            throw new TipoCambioBcbException("La fecha de vigencia publicada por el BCB no es válida: \"{$texto}\".");
        }

        $hoy = CarbonImmutable::now(self::ZONA_HORARIA)->startOfDay();

        if ($fecha->lt($hoy->subDays(30)) || $fecha->gt($hoy->addDays(7))) {
            throw new TipoCambioBcbException("La fecha de vigencia publicada por el BCB ({$texto}) está fuera del rango esperado respecto a hoy ({$hoy->format('Y-m-d')}).");
        }

        return $texto;
    }

    private function extraerMoneda(DOMXPath $xpath, DOMElement $tarjeta): string
    {
        $subtitulo = $this->normalizar($this->texto($xpath, ".//*[contains(concat(' ', normalize-space(@class), ' '), ' bcb-kpi2-sub ')]", $tarjeta));

        foreach (self::MONEDAS as $nombre => $codigo) {
            if (str_contains($subtitulo, 'bolivianos por '.$nombre)) {
                return $codigo;
            }
        }

        throw new TipoCambioBcbException('No se pudo identificar la moneda del tipo de cambio oficial (subtítulo: "'.$subtitulo.'").');
    }

    private function extraerValor(DOMXPath $xpath, DOMElement $tarjeta): string
    {
        $texto = $this->texto($xpath, ".//*[contains(concat(' ', normalize-space(@class), ' '), ' bcb-tco-num ')]", $tarjeta);
        $texto = trim(str_replace("\u{00A0}", ' ', $texto));

        // Formato boliviano: punto para miles y coma decimal (p. ej. "11,75" o "1.234,56789").
        if (! preg_match('/^(\d{1,3}(?:\.\d{3})*|\d+)(?:,(\d+))?$/', $texto, $partes)) {
            throw new TipoCambioBcbException("El valor publicado por el BCB no es numérico: \"{$texto}\". No se modificaron los datos.");
        }

        $entero = ltrim(str_replace('.', '', $partes[1]), '0') ?: '0';
        $decimales = rtrim($partes[2] ?? '', '0');

        if (strlen($entero) > 5 || strlen($decimales) > 5) {
            throw new TipoCambioBcbException("El valor publicado por el BCB ({$texto}) excede la precisión NUMERIC(10,5) de la tabla.");
        }

        $valor = $decimales === '' ? $entero : "{$entero}.{$decimales}";

        if (bccomp($valor, '0', 5) <= 0) {
            throw new TipoCambioBcbException("El valor publicado por el BCB ({$texto}) no es mayor que cero.");
        }

        return $valor;
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument();
        $previo = libxml_use_internal_errors(true);
        $cargado = $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        if (! $cargado) {
            throw new TipoCambioBcbException('No se pudo interpretar el HTML devuelto por el BCB.');
        }

        return new DOMXPath($dom);
    }

    private function texto(DOMXPath $xpath, string $consulta, DOMElement $contexto): string
    {
        $nodos = $xpath->query($consulta, $contexto);

        if ($nodos === false || $nodos->length !== 1) {
            throw new TipoCambioBcbException('La tarjeta "Tipo de cambio oficial" del BCB no tiene la estructura esperada. No se modificaron los datos.');
        }

        return trim(preg_replace('/\s+/u', ' ', $nodos->item(0)->textContent));
    }

    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower($texto, 'UTF-8');

        return strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }

    private function log(): \Psr\Log\LoggerInterface
    {
        return Log::channel('tipo_cambio');
    }
}
