<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Recibo;
use App\Models\Venta;
use App\Models\VentaDetalle;

class ReciboController extends Controller
{
    public function generar(Request $request)
    {
        $ventaId = $request->input('venta_id');
        if ($ventaId) {
            $venta = Venta::find($ventaId);
        } else {
            $venta = Venta::orderBy('id', 'desc')->first();
        }

        if (!$venta) {
            return response()->json(['error' => 'No hay ventas']);
        }

        $total = $venta->monto;
        $subtotal = round($total / 1.18, 2);
        $igv = round($total - $subtotal, 2);
        $numero = 'R' . date('Ymd') . str_pad($venta->id, 6, '0', STR_PAD_LEFT);
        $tipo = $request->input('tipo', 'BOLETA');
        if (!in_array($tipo, ['BOLETA', 'FACTURA'])) {
            $tipo = 'BOLETA';
        }

        $recibo = new Recibo();
        $recibo->venta_id = $venta->id;
        $recibo->numero = $numero;
        $recibo->subtotal = $subtotal;
        $recibo->igv = $igv;
        $recibo->total = $total;
        $recibo->tipo = $tipo;
        $recibo->save();

        // Obtener detalle histórico de la venta
        $detalles = VentaDetalle::where('venta_id', $venta->id)->get()->map(function ($detalle) {
            return [
                'nombre_producto' => $detalle->nombre_producto ?? ($detalle->menu ? $detalle->menu->nombre : 'Producto eliminado'),
                'descripcion_producto' => $detalle->descripcion_producto ?? ($detalle->menu ? $detalle->menu->descripcion : null),
                'categoria_producto' => $detalle->categoria_producto ?? ($detalle->menu ? $detalle->menu->categoria : null),
                'cantidad' => $detalle->cantidad,
                'precio_unitario' => (float) $detalle->precio_unitario,
                'subtotal' => (float) $detalle->subtotal,
            ];
        });

        return response()->json([
            'ok' => true,
            'msg' => 'Recibo generado',
            'recibo_id' => $recibo->id,
            'numero' => $numero,
            'subtotal' => $subtotal,
            'igv' => $igv,
            'total' => $total,
            'tipo' => $tipo,
            'detalles' => $detalles
        ]);
    }
}
