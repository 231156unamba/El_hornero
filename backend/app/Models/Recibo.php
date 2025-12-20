<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recibo extends Model
{
    use HasFactory;

    protected $table = 'recibo';
    public $timestamps = false;

    protected $fillable = [
        'venta_id',
        'numero',
        'subtotal',
        'igv',
        'total',
        'tipo',
        'estado_sunat',
    ];
}
