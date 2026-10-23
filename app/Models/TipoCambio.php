<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TipoCambio extends Model
{
    protected $connection = 'cambios';

    protected $table = 'CAMBIOS';

    protected $primaryKey = 'FECHA';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * Evita el formato `Y-m-d H:i:s.v` del grammar SQL Server, que el driver
     * envía como nvarchar y DATETIME interpreta en DMY (23 como mes).
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    protected $fillable = [
        'FECHA',
        'CAMBIO',
    ];

    protected function casts(): array
    {
        return [
            'FECHA' => 'datetime',
            'CAMBIO' => 'float',
        ];
    }

    public static function fechaMedianoche(string $fechaYmd): string
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $fechaYmd)->format('Y-m-d H:i:s');
    }

    public static function queryPorFechaCalendario(string $fechaSql): Builder
    {
        $query = static::query();

        if ($query->getConnection()->getDriverName() === 'sqlsrv') {
            return $query->whereRaw('CONVERT(date, [FECHA]) = CONVERT(date, ?, 120)', [$fechaSql]);
        }

        return $query->whereDate('FECHA', substr($fechaSql, 0, 10));
    }

    /**
     * @return array{0: string, 1: array{0: string, 1: float}}
     */
    public static function sentenciaInsertar(string $fechaSql, float $cambio, string $driver): array
    {
        if ($driver === 'sqlsrv') {
            return [
                'insert into [CAMBIOS] ([FECHA], [CAMBIO]) values (CONVERT(datetime, ?, 120), ?)',
                [$fechaSql, $cambio],
            ];
        }

        return [
            'insert into "CAMBIOS" ("FECHA", "CAMBIO") values (?, ?)',
            [$fechaSql, $cambio],
        ];
    }

    public static function insertar(string $fechaSql, float $cambio): void
    {
        $conexion = (new static())->getConnection();
        [$sql, $bindings] = static::sentenciaInsertar($fechaSql, $cambio, $conexion->getDriverName());

        if ($conexion->getDriverName() === 'sqlsrv') {
            $conexion->insert($sql, $bindings);

            return;
        }

        static::query()->create([
            'FECHA' => $fechaSql,
            'CAMBIO' => $cambio,
        ]);
    }
}
