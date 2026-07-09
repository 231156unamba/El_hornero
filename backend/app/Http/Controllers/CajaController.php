<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Caja;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\Pedido;
use App\Models\Menu;

class CajaController extends Controller
{
    public function abrir(Request $request)
    {
        $exists = Caja::where('estado', 'ABIERTA')->exists();
        if ($exists) {
            return response()->json(['error' => 'Ya existe una caja abierta']);
        }

        $monto = $request->input('monto', 100.00);
        
        $caja = new Caja();
        $caja->fecha_apertura = now();
        $caja->monto_inicial = $monto;
        $caja->estado = 'ABIERTA';
        $caja->save();

        return response()->json(['ok' => true, 'msg' => 'Caja abierta correctamente', 'id' => $caja->id]);
    }

    public function estado()
    {
        $caja = Caja::orderBy('id', 'desc')->first();
        if ($caja) {
            return response()->json([
                'estado' => $caja->estado,
                'id' => $caja->id,
                'monto_inicial' => (float)$caja->monto_inicial
            ]);
        }
        return response()->json(['estado' => null]);
    }

    public function registrarVenta(Request $request)
    {
        $monto = $request->input('monto', 0.00);
        $pedidoIds = $request->input('pedido_ids', []);
        
        if ($monto <= 0) {
            return response()->json(['error' => 'Monto inválido']);
        }

        $venta = new Venta();
        $venta->fecha = now()->toDateString();
        $venta->monto = $monto;
        $venta->metodo_pago = $request->input('metodo_pago', 'Efectivo');
        $venta->save();

        // Si hay pedidos, guardamos el detalle de la venta
        if (!empty($pedidoIds)) {
            $pedidos = Pedido::whereIn('id', $pedidoIds)->get();
            $menus = Menu::all()->keyBy('nombre');
            
            foreach ($pedidos as $pedido) {
                $detalle = (string) $pedido->detalle;
                $parts = array_map('trim', explode(',', $detalle));
                
                foreach ($parts as $part) {
                    if (preg_match('/(\d+)\s*x\s*(.+)/i', $part, $m)) {
                        $qty = (int) $m[1];
                        $nombre = trim($m[2]);
                        $nombre = preg_replace('/\(.*$/', '', $nombre);
                        
                        if (isset($menus[$nombre])) {
                            $menu = $menus[$nombre];
                            $detalleVenta = new VentaDetalle();
                            $detalleVenta->venta_id = $venta->id;
                            $detalleVenta->menu_id = $menu->id;
                            $detalleVenta->cantidad = $qty;
                            $detalleVenta->precio_unitario = $menu->precio;
                            $detalleVenta->subtotal = $qty * $menu->precio;
                            $detalleVenta->save();
                        }
                    }
                }
            }
        }

        return response()->json([
            'ok' => true, 
            'msg' => 'Venta registrada. Total: S/ ' . number_format($monto, 2), 
            'ventaId' => $venta->id
        ]);
    }

    public function cerrar()
    {
        $caja = Caja::where('estado', 'ABIERTA')->orderBy('id', 'desc')->first();
        if (!$caja) {
            return response()->json(['error' => 'No hay caja abierta']);
        }

        $fechaAp = date('Y-m-d', strtotime($caja->fecha_apertura));
        
        $totalVentas = Venta::where('fecha', '>=', $fechaAp)->sum('monto');
        
        $montoFinal = $caja->monto_inicial + $totalVentas;

        $caja->fecha_cierre = now();
        $caja->monto_final = $montoFinal;
        $caja->estado = 'CERRADA';
        $caja->save();

        return response()->json([
            'ok' => true, 
            'msg' => 'Caja cerrada', 
            'monto_final' => number_format($montoFinal, 2)
        ]);
    }
}
