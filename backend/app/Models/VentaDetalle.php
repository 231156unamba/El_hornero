<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentaDetalle extends Model
{
    use HasFactory;

    protected $table = 'venta_detalle';
    public $timestamps = false;

    protected $fillable = [
        'venta_id',
        'menu_id',
        'nombre_producto',
        'descripcion_producto',
        'categoria_producto',
        'cantidad',
        'precio_unitario',
        'subtotal',
    ];

    // Relación con Venta
    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    // Relación con Menu
    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }
}
