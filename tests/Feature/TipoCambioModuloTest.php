<?php

namespace Tests\Feature;

use App\Models\TipoCambio;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Concerns\PortadaBcb;
use Tests\TestCase;

class TipoCambioModuloTest extends TestCase
{
    use PortadaBcb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-22 21:00:00', 'America/La_Paz'));
        Sleep::fake();
    }

    private function registrar(string $fecha, string $valor): TipoCambio
    {
        return TipoCambio::create([
            'FECHA' => $fecha.' 00:00:00',
            'CAMBIO' => (float) $valor,
        ]);
    }

    private function fakeBcb(string $html, int $estado = 200): void
    {
        Http::swap(new HttpFactory());
        Http::fake(['www.bcb.gob.bo/*' => Http::response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8'])]);
    }

    public function test_la_raiz_muestra_el_dashboard_sin_autenticacion(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Tipo Cambio')
            ->assertSee('Administración')
            ->assertSee('Actualizar')
            ->assertDontSee('Iniciar sesión')
            ->assertDontSee('Login')
            ->assertDontSee('Cerrar sesión');
    }

    public function test_no_existen_rutas_de_autenticacion_ni_dashboard_privado(): void
    {
        $this->get('/login')->assertNotFound();
        $this->get('/register')->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();
        $this->get('/dashboard')->assertNotFound();
        $this->post('/logout')->assertNotFound();

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('login'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('register'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('logout'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('dashboard'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('password.request'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('profile.edit'));
    }

    public function test_consulta_registros_ordenados_por_fecha_descendente(): void
    {
        $this->registrar('2026-10-20', '11.70000');
        $this->registrar('2026-10-22', '11.75000');
        $this->registrar('2026-10-21', '11.72500');

        $this->get(route('tipo-cambio.index'))
            ->assertOk()
            ->assertSeeInOrder(['Fecha', 'Tipo de cambio'])
            ->assertDontSee('Fecha de registro')
            ->assertSeeInOrder(['22/10/2026', '21/10/2026', '20/10/2026'])
            ->assertSee('11,72500')
            ->assertSee('Actualizar');
    }

    public function test_listado_paginado(): void
    {
        $inicio = Carbon::parse('2026-01-01');
        for ($i = 0; $i < 20; $i++) {
            $this->registrar($inicio->copy()->addDays($i)->toDateString(), '6.96000');
        }

        $this->get(route('tipo-cambio.index'))
            ->assertOk()
            ->assertSee('20/01/2026')
            ->assertDontSee('05/01/2026')
            ->assertSee('page=2', false);

        $this->get(route('tipo-cambio.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('05/01/2026')
            ->assertDontSee('20/01/2026');
    }

    public function test_actualizar_registra_el_dato_oficial_del_bcb(): void
    {
        $this->fakeBcb($this->portadaBcb());

        $this->post(route('tipo-cambio.actualizar'))
            ->assertRedirect(route('tipo-cambio.index'))
            ->assertSessionHas('success', 'Tipo de cambio registrado: USD 11.75 con vigencia 22/10/2026.');

        $registro = TipoCambio::sole();
        $this->assertSame('2026-10-22', $registro->FECHA->toDateString());
        $this->assertEqualsWithDelta(11.75, (float) $registro->CAMBIO, 0.0001);
        $this->assertSame('cambios', $registro->getConnectionName());
    }

    public function test_actualizaciones_repetidas_no_duplican_y_corrigen_el_valor(): void
    {
        $this->fakeBcb($this->portadaBcb());
        $this->post(route('tipo-cambio.actualizar'));
        $this->post(route('tipo-cambio.actualizar'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'No hubo cambios'));
        $this->assertSame(1, TipoCambio::count());

        $this->fakeBcb($this->portadaBcb(valor: '11,80'));
        $this->post(route('tipo-cambio.actualizar'))
            ->assertSessionHas('success', fn ($m) => str_starts_with($m, 'Tipo de cambio actualizado'));

        $this->assertSame(1, TipoCambio::count());
        $this->assertEqualsWithDelta(11.80, (float) TipoCambio::sole()->CAMBIO, 0.0001);
    }

    public function test_guarda_la_fecha_de_vigencia_del_dia_siguiente(): void
    {
        $this->fakeBcb($this->portadaBcb(fecha: '2026-10-23'));

        $this->post(route('tipo-cambio.actualizar'));

        $this->assertSame('2026-10-23', TipoCambio::sole()->FECHA->toDateString());
    }

    public function test_inserta_fecha_con_dia_mayor_a_12_normalizada_a_medianoche(): void
    {
        $this->fakeBcb($this->portadaBcb(fecha: '2026-10-23', valor: '11,84'));

        $this->artisan('tipo-cambio:actualizar')
            ->expectsOutputToContain('USD 11.84')
            ->assertSuccessful();

        $registro = TipoCambio::sole();
        $this->assertSame('2026-10-23 00:00:00', $registro->FECHA->format('Y-m-d H:i:s'));
        $this->assertEqualsWithDelta(11.84, (float) $registro->CAMBIO, 0.0001);
    }

    public function test_bcb_no_disponible_informa_error_y_conserva_datos(): void
    {
        $this->registrar('2026-10-21', '11.72500');
        $this->fakeBcb('Service Unavailable', 503);

        $this->post(route('tipo-cambio.actualizar'))
            ->assertRedirect(route('tipo-cambio.index'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'HTTP 503'));

        $this->assertSame(1, TipoCambio::count());
        $this->assertEqualsWithDelta(11.725, (float) TipoCambio::sole()->CAMBIO, 0.0001);
    }

    public function test_error_de_conexion_informa_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->post(route('tipo-cambio.actualizar'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'No se pudo conectar con el BCB'));

        $this->assertSame(0, TipoCambio::count());
    }

    public function test_estructura_cambiada_o_valor_invalido_no_guarda_nada(): void
    {
        $this->fakeBcb($this->portadaSinTarjeta());
        $this->post(route('tipo-cambio.actualizar'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'No se encontró la tarjeta'));

        $this->fakeBcb($this->portadaBcb(valor: 's/d'));
        $this->post(route('tipo-cambio.actualizar'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'no es numérico'));

        $this->assertSame(0, TipoCambio::count());
    }

    public function test_evita_ejecuciones_simultaneas(): void
    {
        Http::fake();
        $lock = Cache::lock('tipo-cambio:bcb:actualizar', 120);
        $this->assertTrue($lock->get());

        $this->post(route('tipo-cambio.actualizar'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'en curso'));

        Http::assertNothingSent();
        $lock->release();
    }

    public function test_comando_programado_usa_el_mismo_servicio(): void
    {
        $this->fakeBcb($this->portadaBcb());
        $this->artisan('tipo-cambio:actualizar')
            ->expectsOutputToContain('USD 11.75')
            ->assertSuccessful();
        $this->artisan('tipo-cambio:actualizar')->assertSuccessful();
        $this->assertSame(1, TipoCambio::count());

        $this->fakeBcb('', 500);
        $this->artisan('tipo-cambio:actualizar')->assertFailed();
        $this->assertSame(1, TipoCambio::count());
    }

    public function test_scheduler_ejecuta_a_las_23_05_hora_de_bolivia(): void
    {
        $evento = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command ?? '', 'tipo-cambio:actualizar'));

        $this->assertNotNull($evento);
        $this->assertSame('5 23 * * *', $evento->expression);
        $this->assertSame('America/La_Paz', $evento->timezone);
        $this->assertTrue($evento->withoutOverlapping);
    }

    public function test_tipos_de_cambio_usan_la_conexion_cambios(): void
    {
        $this->assertSame('cambios', (new TipoCambio())->getConnectionName());
        $this->assertSame('sqlite', TipoCambio::query()->getConnection()->getDriverName());
        $this->assertNotSame('sqlsrv', TipoCambio::query()->getConnection()->getDriverName());
    }

    public function test_actualiza_solo_la_fecha_publicada_y_no_duplica_si_ya_existe_con_hora(): void
    {
        $this->registrar('2026-10-21', '11.70000');
        TipoCambio::create(['FECHA' => '2026-10-22 20:00:00', 'CAMBIO' => 11.70]);

        $this->fakeBcb($this->portadaBcb());
        $this->post(route('tipo-cambio.actualizar'));

        $this->assertSame(2, TipoCambio::count());
        $this->assertEqualsWithDelta(11.70, (float) TipoCambio::query()->whereDate('FECHA', '2026-10-21')->value('CAMBIO'), 0.0001);
        $delDia = TipoCambio::query()->whereDate('FECHA', '2026-10-22')->sole();
        $this->assertEqualsWithDelta(11.75, (float) $delDia->CAMBIO, 0.0001);
        $this->assertSame('20:00:00', $delDia->FECHA->format('H:i:s'));
    }
}
