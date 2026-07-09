<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    use HasFactory;

    protected $table = 'venta';
    public $timestamps = false;

    protected $fillable = [
        'fecha',
        'monto',
        'metodo_pago',
    ];

    // Relación con VentaDetalle
    public function detalles()
    {
        return $this->hasMany(VentaDetalle::class);
    }

    // Relación con Recibo
    public function recibo()
    {
        return $this->hasOne(Recibo::class);
    }

    // Relación con Pedidos
    public function pedidos()
    {
        return $this->hasMany(Pedido::class);
    }
}
