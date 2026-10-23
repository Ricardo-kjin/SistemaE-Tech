<?php

namespace Tests\Unit;

use App\Models\TipoCambio;
use PHPUnit\Framework\TestCase;

class TipoCambioFechaSqlTest extends TestCase
{
    public function test_normaliza_la_fecha_a_medianoche(): void
    {
        $this->assertSame('2026-10-23 00:00:00', TipoCambio::fechaMedianoche('2026-10-23'));
    }

    public function test_el_insert_de_sql_server_usa_convert_120_y_parametros_enlazados(): void
    {
        [$sql, $bindings] = TipoCambio::sentenciaInsertar('2026-10-23 00:00:00', 11.84, 'sqlsrv');

        $this->assertSame(
            'insert into [CAMBIOS] ([FECHA], [CAMBIO]) values (CONVERT(datetime, ?, 120), ?)',
            $sql
        );
        $this->assertSame(['2026-10-23 00:00:00', 11.84], $bindings);
        $this->assertStringNotContainsString('2026-10-23 00:00:00', $sql);
    }
}
