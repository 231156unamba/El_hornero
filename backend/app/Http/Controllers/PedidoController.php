<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pedido;
use App\Models\Menu;

class PedidoController extends Controller
{ 
    public function index()
    {
        $pedidos = Pedido::whereDate('fecha', now()->toDateString())
            ->orderByRaw("CASE WHEN estado = 'preparado' THEN 0 ELSE 1 END")
            ->orderBy('fecha', 'desc')
            ->get()
            ->map(function ($pedido) {
                return [
                    'id' => (int) $pedido->id,
                    'mesa' => (int) $pedido->mesa,
                    'tipo_servicio' => (string) ($pedido->tipo_servicio ?? 'local'),
                    'detalle' => (string) $pedido->detalle,
                    'estado' => (string) $pedido->estado,
                    'fecha' => (string) $pedido->fecha,
                    'costo' => $pedido->calcularTotalCosto(),
                ];
            });
        return response()->json($pedidos);
    }

    public function store(Request $request)
    {
        $request->validate([
            'mesa' => 'required|integer',
            'detalle' => 'required|string',
            'usuario_id' => 'required|integer',
            'tipo_servicio' => 'nullable|string|in:local,llevar',
        ]);

        $pedido = new Pedido();
        $pedido->mesa = $request->mesa;
        $pedido->usuario_id = $request->usuario_id;
        $pedido->detalle = $request->detalle;
        $pedido->tipo_servicio = $request->input('tipo_servicio', 'local');
        $pedido->estado = 'pedido';
        $pedido->fecha = now();
        $pedido->save();

        return response()->json(['success' => true, 'id' => $pedido->id]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|string',
        ]);

        $pedido = Pedido::find($id);
        if ($pedido) {
            $pedido->estado = $request->estado;
            $pedido->save();
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false, 'error' => 'Pedido no encontrado'], 404);
    }

    public function updateStatusFromPost(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'estado' => 'required|string',
            'venta_id' => 'nullable|integer',
        ]);

        $pedido = Pedido::find($request->id);
        if ($pedido) {
            $pedido->estado = $request->estado;
            if ($request->filled('venta_id')) {
                $pedido->venta_id = $request->venta_id;
            }
            $pedido->save();
            return response()->json(['success' => true]);
        }
        return response()->json(['success' => false, 'error' => 'Pedido no encontrado'], 404);
    }

    public function destroy($id)
    {
        $pedido = Pedido::find($id);
        if (!$pedido) {
            return response()->json(['success' => false, 'error' => 'Pedido no encontrado'], 404);
        }
        if (strtolower((string)$pedido->estado) !== 'pedido') {
            return response()->json(['success' => false, 'error' => 'Solo se puede cancelar pedidos en estado pedido'], 409);
        }
        $pedido->delete();
        return response()->json(['success' => true]);
    }
}
