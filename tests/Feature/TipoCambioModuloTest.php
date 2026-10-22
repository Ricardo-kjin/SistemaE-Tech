<?php

namespace Tests\Feature;

use App\Models\TipoCambio;
use App\Models\User;
use App\Services\TipoCambio\BcbTipoCambioService;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PortadaBcb;
use Tests\TestCase;

class TipoCambioModuloTest extends TestCase
{
    use PortadaBcb;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermisosSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-22 21:00:00', 'America/La_Paz'));
        Sleep::fake();
    }

    private function usuarioConRol(string $rol): User
    {
        return User::factory()->create()->assignRole($rol);
    }

    private function registrar(string $fecha, string $valor, string $moneda = 'USD'): TipoCambio
    {
        return TipoCambio::create([
            'fecha' => $fecha,
            'moneda' => $moneda,
            'tipo_cambio' => $valor,
            'fuente' => BcbTipoCambioService::FUENTE,
        ]);
    }

    private function fakeBcb(string $html, int $estado = 200): void
    {
        Http::swap(new HttpFactory());
        Http::fake(['www.bcb.gob.bo/*' => Http::response($html, $estado, ['Content-Type' => 'text/html; charset=utf-8'])]);
    }

    public static function roles(): array
    {
        return [['Administrador'], ['Gerente']];
    }

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get(route('tipo-cambio.index'))->assertRedirect(route('login'));
        $this->post(route('tipo-cambio.actualizar'))->assertRedirect(route('login'));
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_usuario_sin_rol_no_tiene_acceso(): void
    {
        Http::fake();
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get(route('dashboard'))->assertForbidden();
        $this->actingAs($usuario)->get(route('tipo-cambio.index'))->assertForbidden();
        $this->actingAs($usuario)->post(route('tipo-cambio.actualizar'))->assertForbidden();
        Http::assertNothingSent();
    }

    #[DataProvider('roles')]
    public function test_rol_inicia_sesion_y_ve_dashboard_y_menu(string $rol): void
    {
        $usuario = $this->usuarioConRol($rol);

        $this->post(route('login'), ['email' => $usuario->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Administración')
            ->assertSee(route('tipo-cambio.index'), false);
    }

    #[DataProvider('roles')]
    public function test_rol_consulta_registros_ordenados_por_fecha_descendente(string $rol): void
    {
        $this->registrar('2026-10-20', '11.70000');
        $this->registrar('2026-10-22', '11.75000');
        $this->registrar('2026-10-21', '11.72500');

        $this->actingAs($this->usuarioConRol($rol))
            ->get(route('tipo-cambio.index'))
            ->assertOk()
            ->assertSeeInOrder(['Fecha', 'Moneda', 'Tipo de cambio', 'Fuente', 'Fecha de registro'])
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

        $usuario = $this->usuarioConRol('Gerente');

        $this->actingAs($usuario)->get(route('tipo-cambio.index'))
            ->assertOk()
            ->assertSee('20/01/2026')
            ->assertDontSee('05/01/2026')
            ->assertSee('page=2', false);

        $this->actingAs($usuario)->get(route('tipo-cambio.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('05/01/2026')
            ->assertDontSee('20/01/2026');
    }

    #[DataProvider('roles')]
    public function test_actualizar_registra_el_dato_oficial_del_bcb(string $rol): void
    {
        $this->fakeBcb($this->portadaBcb());

        $this->actingAs($this->usuarioConRol($rol))
            ->post(route('tipo-cambio.actualizar'))
            ->assertRedirect(route('tipo-cambio.index'))
            ->assertSessionHas('success', 'Tipo de cambio registrado: USD 11.75 con vigencia 22/10/2026.');

        $registro = TipoCambio::sole();
        $this->assertSame('2026-10-22', $registro->fecha->toDateString());
        $this->assertSame('USD', $registro->moneda);
        $this->assertSame('11.75000', $registro->tipo_cambio);
        $this->assertSame(BcbTipoCambioService::FUENTE, $registro->fuente);
        $this->assertLessThanOrEqual(50, strlen($registro->fuente));
    }

    public function test_actualizaciones_repetidas_no_duplican_y_corrigen_el_valor(): void
    {
        $usuario = $this->usuarioConRol('Administrador');

        $this->fakeBcb($this->portadaBcb());
        $this->actingAs($usuario)->post(route('tipo-cambio.actualizar'));
        $this->actingAs($usuario)->post(route('tipo-cambio.actualizar'))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'No hubo cambios'));
        $this->assertSame(1, TipoCambio::count());

        $this->fakeBcb($this->portadaBcb(valor: '11,80'));
        $this->actingAs($usuario)->post(route('tipo-cambio.actualizar'))
            ->assertSessionHas('success', fn ($m) => str_starts_with($m, 'Tipo de cambio actualizado'));

        $this->assertSame(1, TipoCambio::count());
        $this->assertSame('11.80000', TipoCambio::sole()->tipo_cambio);
    }

    public function test_guarda_la_fecha_de_vigencia_del_dia_siguiente(): void
    {
        $this->fakeBcb($this->portadaBcb(fecha: '2026-10-23'));

        $this->actingAs($this->usuarioConRol('Gerente'))->post(route('tipo-cambio.actualizar'));

        $this->assertSame('2026-10-23', TipoCambio::sole()->fecha->toDateString());
    }

    public function test_bcb_no_disponible_informa_error_y_conserva_datos(): void
    {
        $this->registrar('2026-10-21', '11.72500');
        $this->fakeBcb('Service Unavailable', 503);

        $this->actingAs($this->usuarioConRol('Administrador'))
            ->post(route('tipo-cambio.actualizar'))
            ->assertRedirect(route('tipo-cambio.index'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'HTTP 503'));

        $this->assertSame(1, TipoCambio::count());
        $this->assertSame('11.72500', TipoCambio::sole()->tipo_cambio);
    }

    public function test_error_de_conexion_informa_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->actingAs($this->usuarioConRol('Administrador'))
            ->post(route('tipo-cambio.actualizar'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'No se pudo conectar con el BCB'));

        $this->assertSame(0, TipoCambio::count());
    }

    public function test_estructura_cambiada_o_valor_invalido_no_guarda_nada(): void
    {
        $usuario = $this->usuarioConRol('Administrador');

        $this->fakeBcb($this->portadaSinTarjeta());
        $this->actingAs($usuario)->post(route('tipo-cambio.actualizar'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'No se encontró la tarjeta'));

        $this->fakeBcb($this->portadaBcb(valor: 's/d'));
        $this->actingAs($usuario)->post(route('tipo-cambio.actualizar'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'no es numérico'));

        $this->assertSame(0, TipoCambio::count());
    }

    public function test_evita_ejecuciones_simultaneas(): void
    {
        Http::fake();
        $lock = Cache::lock('tipo-cambio:bcb:actualizar', 120);
        $this->assertTrue($lock->get());

        $this->actingAs($this->usuarioConRol('Administrador'))
            ->post(route('tipo-cambio.actualizar'))
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

    public function test_scheduler_ejecuta_a_las_20_05_hora_de_bolivia(): void
    {
        $evento = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command ?? '', 'tipo-cambio:actualizar'));

        $this->assertNotNull($evento);
        $this->assertSame('5 20 * * *', $evento->expression);
        $this->assertSame('America/La_Paz', $evento->timezone);
        $this->assertTrue($evento->withoutOverlapping);
    }
}
