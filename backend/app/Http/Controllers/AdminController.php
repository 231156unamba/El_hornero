<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Models\Pedido;
use App\Models\Venta;
use App\Models\Usuario;
use App\Models\Recibo;
use App\Models\Configuracion;

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
            ->map(function ($pedido) {
                return [
                    'id' => (int) $pedido->id,
                    'cliente' => 'Mesa '.$pedido->mesa,
                    'fecha' => (string) $pedido->fecha,
                    'total' => null,
                    'estado' => (string) $pedido->estado,
                    'costo' => $pedido->calcularTotalCosto(),
                    'detalle' => (string) $pedido->detalle,
                ];
            });
        return response()->json($data);
    }

    public function clientes()
    {
        $usuarios = Usuario::orderBy('id')->get()->map(function ($usuario) {
            return [
                'id' => (int) $usuario->id,
                'usuario' => (string) $usuario->usuario,
                'nombres' => (string) ($usuario->nombres ?? ''),
                'apellidos' => (string) ($usuario->apellidos ?? ''),
                'tipo' => (string) $usuario->tipo,
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
        $usuario = new Usuario();
        $usuario->usuario = $request->usuario;
        $usuario->nombres = $request->nombres;
        $usuario->apellidos = $request->apellidos;
        $usuario->clave = $request->clave;
        $usuario->tipo = $request->tipo;
        $usuario->save();
        return response()->json(['success' => true, 'id' => $usuario->id]);
    }
    
    public function actualizarUsuario(Request $request, $id)
    {
        $request->validate([
            'usuario' => 'required|string|unique:usuarios,usuario,'.$id,
            'nombres' => 'required|string',
            'apellidos' => 'required|string',
            'tipo' => 'required|string|in:admin,cocina,pedido,caja',
        ]);
        $usuario = Usuario::find($id);
        if (!$usuario) {
            return response()->json(['success' => false, 'error' => 'No encontrado'], 404);
        }
        $usuario->usuario = $request->usuario;
        $usuario->nombres = $request->nombres;
        $usuario->apellidos = $request->apellidos;
        if ($request->filled('clave')) {
            $usuario->clave = $request->clave;
        }
        $usuario->tipo = $request->tipo;
        $usuario->save();
        return response()->json(['success' => true]);
    }
    
    public function eliminarUsuario($id)
    {
        if ((int) $id === 1) {
            return response()->json(['success' => false, 'error' => 'Usuario protegido, no se puede eliminar'], 403);
        }
        $usuario = Usuario::find($id);
        if (!$usuario) {
            return response()->json(['success' => false, 'error' => 'No encontrado'], 404);
        }
        $usuario->delete();
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
            ->map(function ($pedido) {
                return [
                    'id' => (int) $pedido->id,
                    'mesa' => (int) $pedido->mesa,
                    'mesero' => $pedido->mesero ? (string) $pedido->mesero : null,
                    'tipo_servicio' => (string) $pedido->tipo_servicio,
                    'detalle' => (string) $pedido->detalle,
                    'estado' => (string) $pedido->estado,
                    'fecha' => (string) $pedido->fecha,
                    'costo' => $pedido->calcularTotalCosto(),
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
            ->map(function ($pedido) {
                return [
                    'id' => (int) $pedido->id,
                    'mesa' => (int) $pedido->mesa,
                    'mesero' => $pedido->mesero ? (string) $pedido->mesero : null,
                    'detalle' => (string) $pedido->detalle,
                    'estado' => (string) $pedido->estado,
                    'fecha' => (string) $pedido->fecha,
                    'costo' => $pedido->calcularTotalCosto(),
                ];
            });
        return response()->json($rows);
    }

    public function reporteRecibosEntregados(Request $request)
    {
        $query = Recibo::query()->leftJoin('venta', 'venta.id', '=', 'recibo.venta_id');
        
        // Filters
        if ($request->filled('from')) {
            $query->whereDate('recibo.fecha', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('recibo.fecha', '<=', $request->input('to'));
        }
        if ($request->filled('tipo')) {
            $query->where('recibo.tipo', $request->input('tipo'));
        }
        if ($request->filled('numero')) {
            $query->where('recibo.numero', 'like', '%' . $request->input('numero') . '%');
        }
        if ($request->filled('mesa')) {
            $query->whereHas('venta.pedidos', function ($q) use ($request) {
                $q->where('mesa', $request->input('mesa'));
            });
        }
        
        $rows = $query->orderBy('recibo.fecha', 'desc')
            ->select('recibo.*', 'venta.monto as venta_monto', 'venta.fecha as venta_fecha', 'venta.metodo_pago')
            ->distinct()
            ->get()
            ->map(function ($recibo) {
                // Fetch linked orders for details
                $pedidos = Pedido::where('venta_id', $recibo->venta_id)->get();
                $mesas = $pedidos->pluck('mesa')->unique()->implode(', ');
                $detalles = $pedidos->pluck('detalle')->implode('; ');

                return [
                    'id' => (int) $recibo->id,
                    'numero' => (string) $recibo->numero,
                    'fecha' => (string) $recibo->fecha,
                    'tipo' => (string) $recibo->tipo,
                    'estado_sunat' => (string) $recibo->estado_sunat,
                    'subtotal' => (float) $recibo->subtotal,
                    'igv' => (float) $recibo->igv,
                    'total' => (float) $recibo->total,
                    'venta_id' => (int) $recibo->venta_id,
                    'venta_monto' => $recibo->venta_monto !== null ? (float) $recibo->venta_monto : null,
                    'venta_fecha' => $recibo->venta_fecha ? (string) $recibo->venta_fecha : null,
                    'metodo_pago' => (string) $recibo->metodo_pago,
                    'mesa' => $mesas,
                    'detalle' => $detalles,
                ];
            });
        return response()->json($rows);
    }

    public function cajaConfig()
    {
        $data = Configuracion::obtenerTodas();
        // Si no hay datos, insertar los valores por defecto
        if (empty($data)) {
            $defaults = [
                'nombre_comercial' => 'Pollo a la Brasa "El Hornero"',
                'ruc' => '10450610734',
                'direccion' => 'Av. Tamburco N° 224, Tamburco – Abancay – Apurímac',
                'telefono' => '972322520',
                'yape_numero' => '972322520',
            ];
            foreach ($defaults as $clave => $valor) {
                Configuracion::establecer($clave, $valor);
            }
            $data = $defaults;
        }
        return response()->json($data);
    }

    public function updateCajaConfig(Request $request)
    {
        $campos = ['nombre_comercial', 'ruc', 'direccion', 'telefono', 'yape_numero'];
        foreach ($campos as $campo) {
            if ($request->has($campo)) {
                Configuracion::establecer($campo, $request->input($campo));
            }
        }
        if ($request->hasFile('qr')) {
            $file = $request->file('qr');
            $targetDir = public_path('images/qr');
            File::ensureDirectoryExists($targetDir);
            $file->move($targetDir, 'yape.png');
        }
        return response()->json(['success' => true, 'config' => Configuracion::obtenerTodas()]);
    }
}
