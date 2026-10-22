<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoCambio extends Model
{
    protected $connection = 'cambios';

    protected $table = 'CAMBIOS';

    protected $primaryKey = 'FECHA';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

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
}
