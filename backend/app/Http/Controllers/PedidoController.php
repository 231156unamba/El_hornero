<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pedido;

class PedidoController extends Controller
{
    public function index()
    {
        $pedidos = Pedido::orderBy('fecha', 'desc')->get();
        return response()->json($pedidos);
    }

    public function store(Request $request)
    {
        $request->validate([
            'mesa' => 'required|integer',
            'detalle' => 'required|string',
        ]);

        $pedido = new Pedido();
        $pedido->mesa = $request->mesa;
        $pedido->detalle = $request->detalle;
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
}
