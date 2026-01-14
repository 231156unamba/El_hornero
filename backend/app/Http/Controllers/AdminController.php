<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Models\Pedido;
use App\Models\Venta;
use App\Models\Usuario;
use App\Models\Recibo;

class AdminController extends Controller
{
    public function stats()
    {
        $hoy = now()->toDateString();
        $pedidosHoy = Pedido::whereDate('fecha', $hoy)->count();
        $ventasHoy = (float) Venta::whereDate('fecha', $hoy)->sum('monto');
        $totalClientes = Usuario::count();
        $pedidosPendientes = Pedido::where('estado', 'pedido')->count();
        return response()->json([
            'pedidosHoy' => $pedidosHoy,
            'ventasHoy' => $ventasHoy,
            'totalClientes' => $totalClientes,
            'pedidosPendientes' => $pedidosPendientes,
        ]);
    }

    public function recientes()
    {
        $data = Pedido::orderBy('fecha', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($p) {
                return [
                    'id' => (int) $p->id,
                    'cliente' => 'Mesa '.$p->mesa,
                    'fecha' => (string) $p->fecha,
                    'total' => null,
                    'estado' => (string) $p->estado,
                ];
            });
        return response()->json($data);
    }

    public function clientes()
    {
        $usuarios = Usuario::orderBy('id')->get()->map(function ($u) {
            return [
                'id' => (int) $u->id,
                'usuario' => (string) $u->usuario,
                'nombres' => (string) ($u->nombres ?? ''),
                'apellidos' => (string) ($u->apellidos ?? ''),
                'tipo' => (string) $u->tipo,
            ];
        });
        return response()->json($usuarios);
    }
    
    public function crearUsuario(Request $request)
    {
        $request->validate([
            'usuario' => 'required|string|unique:usuarios,usuario',
            'nombres' => 'required|string',
            'apellidos' => 'required|string',
            'clave' => 'required|string',
            'tipo' => 'required|string|in:admin,cocina,pedido,caja',
        ]);
        $u = new Usuario();
        $u->usuario = $request->usuario;
        $u->nombres = $request->nombres;
        $u->apellidos = $request->apellidos;
        $u->clave = $request->clave;
        $u->tipo = $request->tipo;
        $u->save();
        return response()->json(['success' => true, 'id' => $u->id]);
    }
    
    public function actualizarUsuario(Request $request, $id)
    {
        $request->validate([
            'usuario' => 'required|string|unique:usuarios,usuario,'.$id,
            'nombres' => 'required|string',
            'apellidos' => 'required|string',
            'tipo' => 'required|string|in:admin,cocina,pedido,caja',
        ]);
        $u = Usuario::find($id);
        if (!$u) {
            return response()->json(['success' => false, 'error' => 'No encontrado'], 404);
        }
        $u->usuario = $request->usuario;
        $u->nombres = $request->nombres;
        $u->apellidos = $request->apellidos;
        if ($request->filled('clave')) {
            $u->clave = $request->clave;
        }
        $u->tipo = $request->tipo;
        $u->save();
        return response()->json(['success' => true]);
    }
    
    public function eliminarUsuario($id)
    {
        if ((int) $id === 1) {
            return response()->json(['success' => false, 'error' => 'Usuario protegido, no se puede eliminar'], 403);
        }
        $u = Usuario::find($id);
        if (!$u) {
            return response()->json(['success' => false, 'error' => 'No encontrado'], 404);
        }
        $u->delete();
        return response()->json(['success' => true]);
    }

    public function ventasDiarias()
    {
        $rows = Venta::select(DB::raw('DATE(fecha) as label'), DB::raw('SUM(monto) as value'))
            ->groupBy('label')
            ->orderBy('label')
            ->get();
        return response()->json($rows);
    }

    public function ventasMensuales()
    {
        $rows = Venta::select(DB::raw("DATE_FORMAT(fecha, '%Y-%m') as label"), DB::raw('SUM(monto) as value'))
            ->groupBy('label')
            ->orderBy('label')
            ->get();
        return response()->json($rows);
    }

    public function ventasAnuales()
    {
        $rows = Venta::select(DB::raw('YEAR(fecha) as label'), DB::raw('SUM(monto) as value'))
            ->groupBy('label')
            ->orderBy('label')
            ->get();
        return response()->json($rows);
    }

    public function pedidosDiarios()
    {
        $rows = Pedido::select(DB::raw('DATE(fecha) as label'), DB::raw('COUNT(*) as value'))
            ->groupBy('label')
            ->orderBy('label')
            ->get();
        return response()->json($rows);
    }

    public function pedidosMensuales()
    {
        $rows = Pedido::select(DB::raw("DATE_FORMAT(fecha, '%Y-%m') as label"), DB::raw('COUNT(*) as value'))
            ->groupBy('label')
            ->orderBy('label')
            ->get();
        return response()->json($rows);
    }

    public function pedidosAnuales()
    {
        $rows = Pedido::select(DB::raw('YEAR(fecha) as label'), DB::raw('COUNT(*) as value'))
            ->groupBy('label')
            ->orderBy('label')
            ->get();
        return response()->json($rows);
    }

    public function reportePedidos(Request $request)
    {
        $query = Pedido::query()->leftJoin('usuarios', 'usuarios.id', '=', 'pedido.usuario_id');
        if ($request->filled('from')) {
            $query->whereDate('pedido.fecha', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('pedido.fecha', '<=', $request->input('to'));
        }
        if ($request->filled('mesa')) {
            $query->where('pedido.mesa', (int) $request->input('mesa'));
        }
        $rows = $query->orderBy('pedido.fecha', 'desc')
            ->select('pedido.*', DB::raw("CONCAT(usuarios.nombres, ' ', usuarios.apellidos) as mesero"))
            ->get()
            ->map(function ($p) {
                $total = 0.0;
                $detalle = (string) $p->detalle;
                $parts = array_map('trim', explode(',', $detalle));
                foreach ($parts as $part) {
                    if (preg_match('/(\d+)\\s*x\\s*(.+)/i', $part, $m)) {
                        $qty = (int) $m[1];
                        $nombre = trim($m[2]);
                        $nombre = preg_replace('/\\(.*$/', '', $nombre);
                        $menu = \App\Models\Menu::where('nombre', $nombre)->first();
                        if ($menu) {
                            $total += $qty * (float) $menu->precio;
                        }
                        if (preg_match('/S\\/\\.?\\s*([0-9]+(?:\\.[0-9]+)?)/i', $part, $m2)) {
                            $total += (float) $m2[1];
                        }
                    } else {
                        if (preg_match('/S\\/\\.?\\s*([0-9]+(?:\\.[0-9]+)?)/i', $part, $m3)) {
                            $total += (float) $m3[1];
                        }
                    }
                }
                return [
                    'id' => (int) $p->id,
                    'mesa' => (int) $p->mesa,
                    'mesero' => $p->mesero ? (string) $p->mesero : null,
                    'tipo_servicio' => (string) $p->tipo_servicio,
                    'detalle' => (string) $p->detalle,
                    'estado' => (string) $p->estado,
                    'fecha' => (string) $p->fecha,
                    'costo' => round($total, 2),
                ];
            })
            ->filter(function ($row) use ($request) {
                $min = $request->filled('costo_min') ? (float) $request->input('costo_min') : null;
                $max = $request->filled('costo_max') ? (float) $request->input('costo_max') : null;
                if ($min !== null && $row['costo'] < $min) return false;
                if ($max !== null && $row['costo'] > $max) return false;
                return true;
            })
            ->values();
        return response()->json($rows);
    }

    public function reportePedidosPorMesero(Request $request)
    {
        $query = Pedido::query()->leftJoin('usuarios', 'usuarios.id', '=', 'pedido.usuario_id');
        if ($request->filled('from')) {
            $query->whereDate('pedido.fecha', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('pedido.fecha', '<=', $request->input('to'));
        }
        if ($request->filled('mesero_id')) {
            $query->where('pedido.usuario_id', (int) $request->input('mesero_id'));
        }
        $rows = $query->orderBy('pedido.fecha', 'desc')
            ->select('pedido.*', DB::raw("CONCAT(usuarios.nombres, ' ', usuarios.apellidos) as mesero"))
            ->get()
            ->map(function ($p) {
                $total = 0.0;
                $detalle = (string) $p->detalle;
                $parts = array_map('trim', explode(',', $detalle));
                foreach ($parts as $part) {
                    if (preg_match('/(\d+)\\s*x\\s*(.+)/i', $part, $m)) {
                        $qty = (int) $m[1];
                        $nombre = trim($m[2]);
                        $nombre = preg_replace('/\\(.*$/', '', $nombre);
                        $menu = \App\Models\Menu::where('nombre', $nombre)->first();
                        if ($menu) {
                            $total += $qty * (float) $menu->precio;
                        }
                        if (preg_match('/S\\/\\.?\\s*([0-9]+(?:\\.[0-9]+)?)/i', $part, $m2)) {
                            $total += (float) $m2[1];
                        }
                    } else {
                        if (preg_match('/S\\/\\.?\\s*([0-9]+(?:\\.[0-9]+)?)/i', $part, $m3)) {
                            $total += (float) $m3[1];
                        }
                    }
                }
                return [
                    'id' => (int) $p->id,
                    'mesa' => (int) $p->mesa,
                    'mesero' => $p->mesero ? (string) $p->mesero : null,
                    'detalle' => (string) $p->detalle,
                    'estado' => (string) $p->estado,
                    'fecha' => (string) $p->fecha,
                    'costo' => round($total, 2),
                ];
            });
        return response()->json($rows);
    }

    public function reporteRecibosEntregados(Request $request)
    {
        $query = Recibo::query()->leftJoin('venta', 'venta.id', '=', 'recibo.venta_id');
        if ($request->filled('from')) {
            $query->whereDate('recibo.fecha', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('recibo.fecha', '<=', $request->input('to'));
        }
        if ($request->filled('tipo')) {
            $query->where('recibo.tipo', $request->input('tipo'));
        }
        $rows = $query->orderBy('recibo.fecha', 'desc')
            ->select('recibo.*', 'venta.monto as venta_monto', 'venta.fecha as venta_fecha', 'venta.metodo_pago')
            ->get()
            ->map(function ($r) {
                // Fetch linked orders for details
                $pedidos = \App\Models\Pedido::where('venta_id', $r->venta_id)->get();
                $mesas = $pedidos->pluck('mesa')->unique()->implode(', ');
                $detalles = $pedidos->pluck('detalle')->implode('; ');

                return [
                    'id' => (int) $r->id,
                    'numero' => (string) $r->numero,
                    'fecha' => (string) $r->fecha,
                    'tipo' => (string) $r->tipo,
                    'estado_sunat' => (string) $r->estado_sunat,
                    'subtotal' => (float) $r->subtotal,
                    'igv' => (float) $r->igv,
                    'total' => (float) $r->total,
                    'venta_id' => (int) $r->venta_id,
                    'venta_monto' => $r->venta_monto !== null ? (float) $r->venta_monto : null,
                    'venta_fecha' => $r->venta_fecha ? (string) $r->venta_fecha : null,
                    'metodo_pago' => (string) $r->metodo_pago,
                    'mesa' => $mesas,
                    'detalle' => $detalles,
                ];
            });
        return response()->json($rows);
    }

    public function cajaConfig()
    {
        $path = base_path('storage/app/caja-config.json');
        if (!file_exists($path)) {
            $data = [
                'nombre_comercial' => 'Pollo a la Brasa “El Hornero”',
                'ruc' => '10450610734',
                'direccion' => 'Av. Tamburco N° 224, Tamburco – Abancay – Apurímac',
                'telefono' => '972322520',
                'yape_numero' => '972322520',
            ];
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return response()->json($data);
        }
        $json = File::get($path);
        $data = json_decode($json, true) ?: [];
        return response()->json($data);
    }

    public function updateCajaConfig(Request $request)
    {
        $path = base_path('storage/app/caja-config.json');
        $current = [];
        if (file_exists($path)) {
            $current = json_decode(File::get($path), true) ?: [];
        }
        $updated = [
            'nombre_comercial' => $request->input('nombre_comercial', $current['nombre_comercial'] ?? ''),
            'ruc' => $request->input('ruc', $current['ruc'] ?? ''),
            'direccion' => $request->input('direccion', $current['direccion'] ?? ''),
            'telefono' => $request->input('telefono', $current['telefono'] ?? ''),
            'yape_numero' => $request->input('yape_numero', $current['yape_numero'] ?? ''),
        ];
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($updated, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        if ($request->hasFile('qr')) {
            $file = $request->file('qr');
            $targetDir = public_path('images/qr');
            File::ensureDirectoryExists($targetDir);
            $file->move($targetDir, 'yape.png');
        }
        return response()->json(['success' => true, 'config' => $updated]);
    }
}
