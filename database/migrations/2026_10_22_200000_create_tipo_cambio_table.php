<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La tabla ya existe en la base `cambio` (script.sql). Solo se crea si falta,
     * con la misma definición PostgreSQL, para entornos nuevos o de pruebas.
     */
    public function up(): void
    {
        if (Schema::hasTable('tipo_cambio')) {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE TABLE tipo_cambio (
                id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                fecha DATE NOT NULL,
                moneda VARCHAR(3) NOT NULL,
                tipo_cambio NUMERIC(10,5) NOT NULL,
                fuente VARCHAR(50) NOT NULL,
                fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT uk_tipo_cambio UNIQUE (fecha, moneda)
            )
        SQL);
    }

    /**
     * No se elimina la tabla: contiene datos oficiales preexistentes.
     */
    public function down(): void
    {
    }
};
