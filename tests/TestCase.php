<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase recrea el esquema: nunca debe ejecutarse sobre la base real `cambio`.
     */
    protected function beforeRefreshingDatabase()
    {
        $base = config('database.connections.'.config('database.default').'.database');

        if ($base !== 'cambio_test') {
            throw new RuntimeException("Las pruebas solo pueden ejecutarse sobre la base cambio_test (configurada: {$base}).");
        }
    }
}
