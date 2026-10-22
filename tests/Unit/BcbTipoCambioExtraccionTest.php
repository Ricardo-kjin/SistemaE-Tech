<?php

namespace Tests\Unit;

use App\Services\TipoCambio\BcbTipoCambioService;
use App\Services\TipoCambio\TipoCambioBcbException;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\PortadaBcb;

class BcbTipoCambioExtraccionTest extends TestCase
{
    use PortadaBcb;

    private BcbTipoCambioService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-22 21:00:00', 'America/La_Paz'));
        $this->servicio = new BcbTipoCambioService();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_extrae_la_cotizacion_de_la_portada_real(): void
    {
        $cotizacion = $this->servicio->extraer($this->portada());

        $this->assertSame('2026-10-22', $cotizacion->fecha);
        $this->assertSame('USD', $cotizacion->moneda);
        $this->assertSame('11.75', $cotizacion->valor);
    }

    public function test_usa_la_fecha_de_vigencia_publicada_aunque_sea_manana(): void
    {
        $cotizacion = $this->servicio->extraer($this->portada(fecha: '2026-10-23'));

        $this->assertSame('2026-10-23', $cotizacion->fecha);
    }

    public function test_convierte_miles_y_cinco_decimales(): void
    {
        $this->assertSame('1234.56789', $this->servicio->extraer($this->portada(valor: '1.234,56789'))->valor);
        $this->assertSame('6.96', $this->servicio->extraer($this->portada(valor: '6,96000'))->valor);
    }

    #[DataProvider('htmlInvalido')]
    public function test_rechaza_contenido_que_no_puede_validarse(?string $valor, ?string $fecha, ?string $subtitulo): void
    {
        $this->expectException(TipoCambioBcbException::class);

        $this->servicio->extraer($this->portada($valor, $fecha, $subtitulo));
    }

    public static function htmlInvalido(): array
    {
        return [
            'valor vacío' => ['', null, null],
            'valor no numérico' => ['s/d', null, null],
            'valor cero' => ['0,00', null, null],
            'valor negativo' => ['-11,75', null, null],
            'más de cinco decimales' => ['11,123456', null, null],
            'excede NUMERIC(10,5)' => ['123.456,7', null, null],
            'formato con punto decimal ambiguo' => ['11.75', null, null],
            'fecha inexistente' => [null, '2026-02-30', null],
            'fecha con otro formato' => [null, '22/10/2026', null],
            'fecha muy futura' => [null, '2026-11-30', null],
            'fecha muy antigua' => [null, '2026-01-01', null],
            'moneda desconocida' => [null, null, 'Bolivianos por euro'],
        ];
    }

    public function test_falla_si_desaparece_la_tarjeta_oficial(): void
    {
        $this->expectException(TipoCambioBcbException::class);
        $this->expectExceptionMessage('No se encontró la tarjeta');

        $this->servicio->extraer($this->portadaSinTarjeta());
    }

    public function test_falla_con_html_que_no_es_la_portada(): void
    {
        $this->expectException(TipoCambioBcbException::class);

        $this->servicio->extraer('<html><body><h1>Sitio en mantenimiento</h1></body></html>');
    }

    private function portada(?string $valor = null, ?string $fecha = null, ?string $subtitulo = null): string
    {
        return $this->portadaBcb($valor, $fecha, $subtitulo);
    }
}
