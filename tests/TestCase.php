<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.connections.cambios.driver') === 'sqlite') {
            $this->crearTablaCambiosDePrueba();
        }
    }

    /**
     * RefreshDatabase recrea el esquema de usuarios: nunca sobre `cambio` ni sobre SQL Server CAMBIOS.
     */
    protected function beforeRefreshingDatabase()
    {
        $base = config('database.connections.'.config('database.default').'.database');

        if ($base !== 'cambio_test') {
            throw new RuntimeException("Las pruebas de usuarios solo pueden ejecutarse sobre cambio_test (configurada: {$base}).");
        }

        $cambios = config('database.connections.cambios');

        if (($cambios['driver'] ?? '') === 'sqlsrv') {
            throw new RuntimeException('Las pruebas no pueden usar el SQL Server real de CAMBIOS.');
        }
    }

    protected function afterRefreshingDatabase()
    {
        $this->crearTablaCambiosDePrueba();
    }

    protected function crearTablaCambiosDePrueba(): void
    {
        $ruta = config('database.connections.cambios.database');
        if (is_string($ruta) && $ruta !== ':memory:' && ! file_exists($ruta)) {
            touch($ruta);
        }

        Schema::connection('cambios')->dropIfExists('CAMBIOS');
        Schema::connection('cambios')->create('CAMBIOS', function (Blueprint $table) {
            $table->dateTime('FECHA')->primary();
            $table->float('CAMBIO');
        });
    }
}
