<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pedido;
use App\Models\Menu;

class PedidoController extends Controller
{
    public function index()
    {
        $pedidos = Pedido::orderBy('fecha', 'desc')->get()->map(function ($p) {
            $total = 0.0;
            $detalle = (string) $p->detalle;
            $parts = array_map('trim', explode(',', $detalle));
            foreach ($parts as $part) {
                if (preg_match('/(\d+)\s*x\s*(.+)/i', $part, $m)) {
                    $qty = (int) $m[1];
                    $nombre = trim($m[2]);
                    $nombre = preg_replace('/\(.*$/', '', $nombre);
                    $menu = Menu::where('nombre', $nombre)->first();
                    if ($menu) {
                        $total += $qty * (float) $menu->precio;
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
            return [
                'id' => (int) $p->id,
                'mesa' => (int) $p->mesa,
                'tipo_servicio' => (string) ($p->tipo_servicio ?? 'local'),
                'detalle' => (string) $p->detalle,
                'estado' => (string) $p->estado,
                'fecha' => (string) $p->fecha,
                'costo' => round($total, 2),
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
        ]);

        $pedido = Pedido::find($request->id);
        if ($pedido) {
            $pedido->estado = $request->estado;
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
