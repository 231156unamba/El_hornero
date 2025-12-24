<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Pedido;
use App\Models\Venta;
use App\Models\Usuario;

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
                'tipo' => (string) $u->tipo,
            ];
        });
        return response()->json($usuarios);
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
}
