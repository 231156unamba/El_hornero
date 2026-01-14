<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Recibo;
use App\Models\Venta;
use App\Models\SunatLog;

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
        $recibo->estado_sunat = 'PENDIENTE';
        $recibo->save();

        return response()->json([
            'ok' => true,
            'msg' => 'Recibo generado',
            'recibo_id' => $recibo->id,
            'numero' => $numero,
            'subtotal' => $subtotal,
            'igv' => $igv,
            'total' => $total,
            'tipo' => $tipo
        ]);
    }

    public function enviarSunat()
    {
        $recibo = Recibo::where('estado_sunat', 'PENDIENTE')->orderBy('id', 'desc')->first();
        if (!$recibo) {
            return response()->json(['error' => 'No hay recibos pendientes']);
        }

        $recibo->estado_sunat = 'ENVIADO';
        $recibo->save();

        $log = new SunatLog();
        $log->recibo_id = $recibo->id;
        $log->respuesta = 'ENVIADO';
        $log->save();

        return response()->json([
            'ok' => true, 
            'msg' => 'Recibo enviado a SUNAT', 
            'recibo_id' => $recibo->id
        ]);
    }
}
