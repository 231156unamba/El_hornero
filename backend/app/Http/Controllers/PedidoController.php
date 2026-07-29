<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pedido;
use App\Models\Usuario;

class PedidoController extends Controller
{
    private function resolveRole(Usuario $user): string
    {
        $tipo = strtolower((string) $user->tipo);

        if (in_array($tipo, ['admin'], true)) {
            return 'admin';
        }
        if (in_array($tipo, ['caja', 'encargado', 'encargado_caja'], true)) {
            return 'caja';
        }
        if (in_array($tipo, ['cocina', 'kitchen'], true)) {
            return 'cocina';
        }
        if (in_array($tipo, ['pedido', 'mozo'], true)) {
            return 'pedido';
        }

        return $tipo;
    }

    private function formatPedido(Pedido $pedido, string $hoy): array
    {
        return [
            'id'            => (int)    $pedido->id,
            'mesa'          => (int)    $pedido->mesa,
            'tipo_servicio' => (string) ($pedido->tipo_servicio ?? 'local'),
            'detalle'       => (string) $pedido->detalle,
            'estado'        => (string) $pedido->estado,
            'fecha'         => (string) $pedido->fecha,
            'costo'         => $pedido->calcularTotalCosto(),
            'es_anterior'   => $pedido->fecha && date('Y-m-d', strtotime($pedido->fecha)) < $hoy,
        ];
    }

    private function baseQuery(string $hoy)
    {
        return Pedido::where(function ($q) use ($hoy) {
            $q->whereNotIn('estado', ['pagado', 'cancelado'])
              ->orWhereDate('fecha', $hoy);
        });
    }

    private function forbidden(string $message)
    {
        return response()->json(['success' => false, 'error' => $message], 403);
    }

    private function assertMeseroOwnsPedido(Usuario $user, Pedido $pedido): ?\Illuminate\Http\JsonResponse
    {
        if ((int) $pedido->usuario_id !== (int) $user->id) {
            return $this->forbidden('No tiene permiso para operar sobre este pedido.');
        }

        return null;
    }

    private function assertTransitionAllowed(string $role, string $from, string $to): ?\Illuminate\Http\JsonResponse
    {
        $from = strtolower($from);
        $to   = strtolower($to);

        $allowed = match ($role) {
            'cocina' => [
                'pedido'    => ['cocinando'],
                'cocinando' => ['preparado'],
            ],
            'pedido' => [
                'preparado' => ['entregado'],
            ],
            'caja' => [
                'entregado' => ['pagado'],
            ],
            'admin' => null,
            default => [],
        };

        if ($allowed === null) {
            return null;
        }

        if (!isset($allowed[$from]) || !in_array($to, $allowed[$from], true)) {
            return $this->forbidden("Transición de estado no permitida: {$from} → {$to}.");
        }

        return null;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $role = $this->resolveRole($user);
        $hoy  = now()->toDateString();

        $query = $this->baseQuery($hoy);

        if ($role === 'pedido') {
            $query->where('usuario_id', $user->id);
        }

        $pedidos = $query
            ->orderByRaw("CASE WHEN estado = 'preparado' THEN 0 WHEN estado = 'cocinando' THEN 1 WHEN estado = 'pedido' THEN 2 ELSE 3 END")
            ->orderBy('fecha', 'asc')
            ->get()
            ->map(fn ($pedido) => $this->formatPedido($pedido, $hoy));

        return response()->json($pedidos);
    }

    public function mesasOcupadas(Request $request)
    {
        $user = $request->user();
        $role = $this->resolveRole($user);

        if ($role !== 'pedido') {
            return $this->forbidden('Solo los meseros pueden consultar mesas ocupadas.');
        }

        $mesas = Pedido::whereNotIn('estado', ['pagado', 'cancelado'])
            ->distinct()
            ->orderBy('mesa')
            ->pluck('mesa')
            ->map(fn ($mesa) => (int) $mesa)
            ->values();

        return response()->json($mesas);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $role = $this->resolveRole($user);

        if ($role !== 'pedido') {
            return $this->forbidden('Solo los meseros pueden crear pedidos.');
        }

        $request->validate([
            'mesa'          => 'required|integer',
            'detalle'       => 'required|string',
            'tipo_servicio' => 'nullable|string|in:local,llevar',
        ]);

        $pedido = new Pedido();
        $pedido->mesa          = $request->mesa;
        $pedido->usuario_id    = $user->id;
        $pedido->detalle       = $request->detalle;
        $pedido->tipo_servicio = $request->input('tipo_servicio', 'local');
        $pedido->estado        = 'pedido';
        $pedido->fecha         = now();
        $pedido->save();

        return response()->json(['success' => true, 'id' => $pedido->id]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|string',
        ]);

        $pedido = Pedido::find($id);
        if (!$pedido) {
            return response()->json(['success' => false, 'error' => 'Pedido no encontrado'], 404);
        }

        $user     = $request->user();
        $role     = $this->resolveRole($user);
        $nuevoEstado = strtolower((string) $request->estado);
        $estadoActual = strtolower((string) $pedido->estado);

        if ($response = $this->assertTransitionAllowed($role, $estadoActual, $nuevoEstado)) {
            return $response;
        }

        if ($role === 'pedido') {
            if ($response = $this->assertMeseroOwnsPedido($user, $pedido)) {
                return $response;
            }
        }

        $pedido->estado = $nuevoEstado;
        $pedido->save();

        return response()->json(['success' => true]);
    }

    public function updateStatusFromPost(Request $request)
    {
        $request->validate([
            'id'       => 'required|integer',
            'estado'   => 'required|string',
            'venta_id' => 'nullable|integer',
        ]);

        $pedido = Pedido::find($request->id);
        if (!$pedido) {
            return response()->json(['success' => false, 'error' => 'Pedido no encontrado'], 404);
        }

        $user         = $request->user();
        $role         = $this->resolveRole($user);
        $nuevoEstado  = strtolower((string) $request->estado);
        $estadoActual = strtolower((string) $pedido->estado);

        if ($response = $this->assertTransitionAllowed($role, $estadoActual, $nuevoEstado)) {
            return $response;
        }

        if ($role === 'pedido') {
            if ($response = $this->assertMeseroOwnsPedido($user, $pedido)) {
                return $response;
            }
        }

        $pedido->estado = $nuevoEstado;
        if ($request->filled('venta_id')) {
            $pedido->venta_id = $request->venta_id;
        }
        $pedido->save();

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, $id)
    {
        $pedido = Pedido::find($id);
        if (!$pedido) {
            return response()->json(['success' => false, 'error' => 'Pedido no encontrado'], 404);
        }

        $user = $request->user();
        $role = $this->resolveRole($user);

        if (!in_array($role, ['pedido', 'admin'], true)) {
            return $this->forbidden('No tiene permiso para cancelar pedidos.');
        }

        if ($role === 'pedido') {
            if ($response = $this->assertMeseroOwnsPedido($user, $pedido)) {
                return $response;
            }
        }

        if (strtolower((string) $pedido->estado) !== 'pedido') {
            return response()->json(['success' => false, 'error' => 'Solo se puede cancelar pedidos en estado pedido'], 409);
        }

        $pedido->delete();

        return response()->json(['success' => true]);
    }
}
