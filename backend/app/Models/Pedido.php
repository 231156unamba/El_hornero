<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    use HasFactory;

    protected $table = 'pedido';
    public $timestamps = false;

    protected $fillable = [
        'mesa',
        'usuario_id',
        'detalle',
        'tipo_servicio',
        'estado',
        'fecha',
        'venta_id',
    ];

    public function calcularTotalCosto(): float
    {
        $total = 0.0;
        $detalle = (string) $this->detalle;
        $parts = array_map('trim', explode(',', $detalle));
        
        // Cargamos todos los menús una sola vez para evitar N+1 queries
        $menus = Menu::all()->keyBy('nombre');
        
        foreach ($parts as $part) {
            if (preg_match('/(\d+)\s*x\s*(.+)/i', $part, $m)) {
                $qty = (int) $m[1];
                $nombre = trim($m[2]);
                $nombre = preg_replace('/\(.*$/', '', $nombre);
                if (isset($menus[$nombre])) {
                    $total += $qty * (float) $menus[$nombre]->precio;
                }
                if (preg_match('/S\/\.?\s*([0-9]+(?:\.[0-9]+)?)/i', $part, $m2)) {
                    $total += (float) $m2[1];
                }
            } else {
                if (preg_match('/S\/\.?\s*([0-9]+(?:\.[0-9]+)?)/i', $part, $m3)) {
                    $total += (float) $m3[1];
                }
            }
        }
        
        return round($total, 2);
    }
}
