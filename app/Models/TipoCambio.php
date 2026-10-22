<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoCambio extends Model
{
    protected $table = 'tipo_cambio';

    public $timestamps = false;

    protected $fillable = [
        'fecha',
        'moneda',
        'tipo_cambio',
        'fuente',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'tipo_cambio' => 'decimal:5',
            'fecha_registro' => 'datetime',
        ];
    }
}
