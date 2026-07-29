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
use App\Models\Menu;
use App\Services\Graficas\PagosPorMetodoGrafica;
use App\Services\Graficas\VentasDiariasGrafica;
use App\Services\Graficas\VentasMensualesGrafica;
use App\Services\Graficas\VentasAnualesGrafica;
use App\Services\Graficas\PedidosDiariosGrafica;
use App\Services\Graficas\PedidosMensualesGrafica;
use App\Services\Graficas\PedidosAnualesGrafica;

class AdminController extends Controller
{
    // ─────────────────────────────────────────────────────────
    //  DASHBOARD
    // ─────────────────────────────────────────────────────────

    /**
     * Endpoint unificado del dashboard — 1 sola llamada HTTP.
     * Cada gráfica devuelve {label, value, count} para mostrar
     * tooltips informativos ("3 ventas — S/. 102.00").
     */
    public function dashboardData()
    {
        $hoy       = now()->toDateString();
        $ayer      = now()->subDay()->toDateString();
        $semIni    = now()->startOfWeek()->toDateString();
        $semAntIni = now()->subWeek()->startOfWeek()->toDateString();
        $semAntFin = now()->subWeek()->endOfWeek()->toDateString();
        $mesAct    = now()->month;
        $anioAct   = now()->year;
        $mesAnt    = now()->subMonth()->month;
        $anioAnt   = now()->subMonth()->year;

        $pct = fn($a, $b) => $b == 0 ? ($a > 0 ? 100 : 0) : round((($a - $b) / $b) * 100, 1);

        // ── KPIs ──────────────────────────────────────────────
        $ventasHoy    = (float) Venta::whereDate('fecha', $hoy)->sum('monto');
        $ventasAyer   = (float) Venta::whereDate('fecha', $ayer)->sum('monto');
        $ventasSem    = (float) Venta::whereBetween('fecha', [$semIni, $hoy])->sum('monto');
        $ventasSemAnt = (float) Venta::whereBetween('fecha', [$semAntIni, $semAntFin])->sum('monto');
        $ventasMes    = (float) Venta::whereMonth('fecha', $mesAct)->whereYear('fecha', $anioAct)->sum('monto');
        $ventasMesAnt = (float) Venta::whereMonth('fecha', $mesAnt)->whereYear('fecha', $anioAnt)->sum('monto');
        $pedidosHoy   = Pedido::whereDate('fecha', $hoy)->count();
        $pedidosAyer  = Pedido::whereDate('fecha', $ayer)->count();
        $ticketHoy    = $pedidosHoy  > 0 ? round($ventasHoy  / $pedidosHoy,  2) : 0;
        $ticketAyer   = $pedidosAyer > 0 ? round($ventasAyer / $pedidosAyer, 2) : 0;
        $unidadesHoy  = (int) DB::table('venta_detalle')
            ->join('venta', 'venta.id', '=', 'venta_detalle.venta_id')
            ->whereDate('venta.fecha', $hoy)->sum('venta_detalle.cantidad');
        $unidadesAyer = (int) DB::table('venta_detalle')
            ->join('venta', 'venta.id', '=', 'venta_detalle.venta_id')
            ->whereDate('venta.fecha', $ayer)->sum('venta_detalle.cantidad');

        $kpis = [
            'ventasHoy'      => $ventasHoy,   'ventasHoyPct'    => $pct($ventasHoy,   $ventasAyer),
            'ventasSemana'   => $ventasSem,   'ventasSemanaPct' => $pct($ventasSem,   $ventasSemAnt),
            'ventasMes'      => $ventasMes,   'ventasMesPct'    => $pct($ventasMes,   $ventasMesAnt),
            'pedidosHoy'     => $pedidosHoy,  'pedidosHoyPct'   => $pct($pedidosHoy,  $pedidosAyer),
            'ticketPromedio' => $ticketHoy,   'ticketPct'       => $pct($ticketHoy,   $ticketAyer),
            'productosHoy'   => $unidadesHoy, 'productosPct'    => $pct($unidadesHoy, $unidadesAyer),
        ];

        // ── Ventas diarias — últimos 7 días con ceros ────────
        $diasLabels = [];
        $diasMap    = [];
        for ($i = 6; $i >= 0; $i--) {
            $d            = now()->subDays($i)->format('Y-m-d');
            $diasLabels[] = $d;
            $diasMap[$d]  = ['value' => 0.0, 'count' => 0];
        }
        $ventasRango = DB::table('venta')
            ->whereBetween('fecha', [now()->subDays(6)->format('Y-m-d'), now()->format('Y-m-d')])
            ->select(
                DB::raw("DATE_FORMAT(fecha, '%Y-%m-%d') as dia"),
                DB::raw('SUM(monto) as value'),
                DB::raw('COUNT(*) as cnt')
            )
            ->groupBy(DB::raw("DATE_FORMAT(fecha, '%Y-%m-%d')"))
            ->get();

        foreach ($ventasRango as $r) {
            $key = (string) $r->dia;
            if (array_key_exists($key, $diasMap)) {
                $diasMap[$key] = ['value' => (float) $r->value, 'count' => (int) $r->cnt];
            }
        }

        $ventasDiarias = collect($diasLabels)->map(fn($d) => [
            'label' => $d,
            'value' => $diasMap[$d]['value'],
            'count' => $diasMap[$d]['count'],
        ])->values();

        // ── Ventas semanales — últimas 6 semanas con ceros ────
        $semanas = [];
        for ($i = 5; $i >= 0; $i--) {
            $ini           = now()->subWeeks($i)->startOfWeek();
            $key           = (int) $ini->format('oW');
            $semanas[$key] = ['label' => $ini->format('d/m'), 'value' => 0.0, 'count' => 0];
        }
        DB::table('venta')
            ->where('fecha', '>=', now()->subWeeks(5)->startOfWeek()->format('Y-m-d'))
            ->select(
                DB::raw('YEARWEEK(fecha, 1) as sk'),
                DB::raw('SUM(monto) as value'),
                DB::raw('COUNT(*) as cnt')
            )
            ->groupBy(DB::raw('YEARWEEK(fecha, 1)'))
            ->get()
            ->each(function ($r) use (&$semanas) {
                $key = (int) $r->sk;
                if (array_key_exists($key, $semanas)) {
                    $semanas[$key]['value'] = (float) $r->value;
                    $semanas[$key]['count'] = (int)   $r->cnt;
                }
            });
        $ventasSemanales = collect(array_values($semanas))->values();

        // ── Ventas mensuales — últimos 6 meses con ceros ──────
        $meses = [];
        for ($i = 5; $i >= 0; $i--) {
            $fecha        = now()->subMonths($i)->startOfMonth();
            $key          = $fecha->format('Y-m');
            $meses[$key]  = ['label' => $fecha->locale('es')->isoFormat('MMM YYYY'), 'value' => 0.0, 'count' => 0];
        }
        DB::table('venta')
            ->where('fecha', '>=', now()->subMonths(5)->startOfMonth()->format('Y-m-d'))
            ->select(
                DB::raw("DATE_FORMAT(fecha, '%Y-%m') as mes_key"),
                DB::raw('SUM(monto) as value'),
                DB::raw('COUNT(*) as cnt')
            )
            ->groupBy(DB::raw("DATE_FORMAT(fecha, '%Y-%m')"))
            ->get()
            ->each(function ($r) use (&$meses) {
                $key = (string) $r->mes_key;
                if (array_key_exists($key, $meses)) {
                    $meses[$key]['value'] = (float) $r->value;
                    $meses[$key]['count'] = (int)   $r->cnt;
                }
            });
        $ventasMensuales = collect(array_values($meses))->values();

        // ── Métodos de pago — histórico completo ─────────────
        $pagos = DB::table('venta')
            ->whereNotNull('metodo_pago')
            ->select('metodo_pago as label', DB::raw('SUM(monto) as value'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('metodo_pago')
            ->orderByDesc('value')
            ->get()
            ->map(fn($r) => ['label' => $r->label, 'value' => (float)$r->value, 'count' => (int)$r->cnt])
            ->values();

        // ── Pedidos por hora — hoy (cantidad de pedidos, no monto) ─
        $horaRows = DB::table('pedido')
            ->whereDate('fecha', $hoy)
            ->select(DB::raw('HOUR(fecha) as hora'), DB::raw('COUNT(*) as cnt'))
            ->groupBy(DB::raw('HOUR(fecha)'))
            ->orderBy('hora')
            ->get();

        $horaData = array_fill(0, 24, 0);
        $horaCnt  = array_fill(0, 24, 0);
        foreach ($horaRows as $r) {
            $horaData[(int)$r->hora] = (int) $r->cnt;
            $horaCnt[(int)$r->hora]  = (int) $r->cnt;
        }

        // ── Más vendidos — top 5 con ventas (últimos 30 días) ─
        $masVendidos = DB::table('venta_detalle')
            ->join('venta', 'venta.id', '=', 'venta_detalle.venta_id')
            ->join('menu',  'menu.id',  '=', 'venta_detalle.menu_id')
            ->where('venta.fecha', '>=', now()->subDays(29)->toDateString())
            ->select('menu.id', 'menu.nombre', DB::raw('SUM(venta_detalle.cantidad) as total'))
            ->groupBy('menu.id', 'menu.nombre')
            ->orderByDesc('total')
            ->limit(5)
            ->get()->values();

        // IDs de los más vendidos para excluirlos de los menos vendidos
        $idsTop = $masVendidos->pluck('id')->toArray();

        // Limpiar el 'id' del response de masVendidos (no lo necesita el frontend)
        $masVendidos = $masVendidos->map(fn($r) => ['nombre' => $r->nombre, 'total' => $r->total])->values();

        // ── Menos vendidos — productos que NO están en el top,
        //    ordenados por menos unidades vendidas (incluye 0)
        //    máximo 5 resultados ──
        $menosVendidos = DB::table('menu')
            ->leftJoin('venta_detalle', 'venta_detalle.menu_id', '=', 'menu.id')
            ->leftJoin('venta', function ($join) {
                $join->on('venta.id', '=', 'venta_detalle.venta_id')
                     ->where('venta.fecha', '>=', now()->subDays(29)->toDateString());
            })
            ->whereNotIn('menu.id', $idsTop)
            ->select('menu.nombre', DB::raw('COALESCE(SUM(venta_detalle.cantidad), 0) as total'))
            ->groupBy('menu.id', 'menu.nombre')
            ->orderBy('total')
            ->limit(5)
            ->get()->values();

        // ── Ventas por categoría — histórico completo ────────
        $categorias = DB::table('venta_detalle')
            ->join('venta', 'venta.id', '=', 'venta_detalle.venta_id')
            ->join('menu',  'menu.id',  '=', 'venta_detalle.menu_id')
            ->select('menu.categoria', DB::raw('SUM(venta_detalle.subtotal) as total'), DB::raw('SUM(venta_detalle.cantidad) as unidades'))
            ->groupBy('menu.categoria')
            ->orderByDesc('total')
            ->get()->values();

        // ── Estado de pedidos — hoy ───────────────────────────
        $estados = DB::table('pedido')
            ->whereDate('fecha', $hoy)
            ->select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->get()->values();

        // ── Últimos 8 pedidos ─────────────────────────────────
        $recientes = Pedido::orderBy('fecha', 'desc')->limit(8)->get()
            ->map(fn($p) => [
                'id'      => (int)    $p->id,
                'cliente' => 'Mesa ' . $p->mesa,
                'fecha'   => (string) $p->fecha,
                'estado'  => (string) $p->estado,
                'detalle' => (string) $p->detalle,
            ])->values();

        // ── Productos con descuento activo ───────────────────
        $descuentos = Menu::whereNotNull('discount_percentage')
            ->where('discount_percentage', '>', 0)
            ->where(fn($q) => $q->whereNull('discount_expires_at')->orWhere('discount_expires_at', '>=', now()))
            ->orderBy('nombre')->get()
            ->map(fn($m) => [
                'id'          => (int)   $m->id,
                'nombre'      =>         $m->nombre,
                'categoria'   =>         $m->categoria,
                'precio'      => (float) $m->precio,
                'descuento'   => (float) $m->discount_percentage,
                'precio_final'=> round($m->precio * (1 - $m->discount_percentage / 100), 2),
                'vence'       => $m->discount_expires_at ? $m->discount_expires_at->toDateTimeString() : null,
            ])->values();

        // ── Ranking de meseros — mes actual ──────────────────
        $meseros = DB::table('pedido')
            ->join('usuarios', 'usuarios.id', '=', 'pedido.usuario_id')
            ->whereMonth('pedido.fecha', $mesAct)->whereYear('pedido.fecha', $anioAct)
            ->select(
                'usuarios.id',
                DB::raw("CONCAT(usuarios.nombres, ' ', usuarios.apellidos) as nombre"),
                'usuarios.usuario',
                DB::raw('COUNT(pedido.id) as total_pedidos'),
                DB::raw("SUM(CASE WHEN pedido.estado = 'pagado' THEN 1 ELSE 0 END) as pedidos_pagados")
            )
            ->groupBy('usuarios.id', 'usuarios.nombres', 'usuarios.apellidos', 'usuarios.usuario')
            ->orderByDesc('total_pedidos')
            ->get()->values();

        return response()->json([
            'kpis'           => $kpis,
            'ventasDiarias'  => $ventasDiarias,
            'ventasSemanales'=> $ventasSemanales,
            'ventasMensuales'=> $ventasMensuales,
            'pagos'          => $pagos,
            'ventasPorHora'  => [
                'labels' => array_map(fn($h) => sprintf('%02d:00', $h), range(0, 23)),
                'data'   => array_values($horaData),
                'counts' => array_values($horaCnt),
            ],
            'masVendidos'    => $masVendidos,
            'menosVendidos'  => $menosVendidos,
            'categorias'     => $categorias,
            'estados'        => $estados,
            'recientes'      => $recientes,
            'descuentos'     => $descuentos,
            'meseros'        => $meseros,
        ]);
    }

    public function stats()
    {
        $hoy = now()->toDateString();
        return response()->json([
            'pedidosHoy'    => Pedido::whereDate('fecha', $hoy)->count(),
            'ventasHoy'     => (float) Venta::whereDate('fecha', $hoy)->sum('monto'),
            'totalClientes' => Usuario::count(),
            'ventasMes'     => (float) Venta::whereMonth('fecha', now()->month)->whereYear('fecha', now()->year)->sum('monto'),
            'recibosHoy'    => Recibo::whereDate('fecha', $hoy)->count(),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    //  USUARIOS (CRUD)
    // ─────────────────────────────────────────────────────────

    public function clientes()
    {
        return response()->json(
            Usuario::orderBy('id')->get()->map(fn($u) => [
                'id'        => (int)    $u->id,
                'usuario'   => (string) $u->usuario,
                'nombres'   => (string) ($u->nombres   ?? ''),
                'apellidos' => (string) ($u->apellidos ?? ''),
                'tipo'      => (string) $u->tipo,
            ])
        );
    }

    public function crearUsuario(Request $request)
    {
        $request->validate([
            'usuario'   => 'required|string|unique:usuarios,usuario',
            'nombres'   => 'required|string',
            'apellidos' => 'required|string',
            'clave'     => 'required|string',
            'tipo'      => 'required|string|in:admin,cocina,pedido,caja',
        ]);
        $u            = new Usuario();
        $u->usuario   = $request->usuario;
        $u->nombres   = $request->nombres;
        $u->apellidos = $request->apellidos;
        $u->clave     = \Illuminate\Support\Facades\Hash::make($request->clave);
        $u->tipo      = $request->tipo;
        $u->save();
        return response()->json(['success' => true, 'id' => $u->id]);
    }

    public function actualizarUsuario(Request $request, $id)
    {
        $request->validate([
            'usuario'   => 'required|string|unique:usuarios,usuario,' . $id,
            'nombres'   => 'required|string',
            'apellidos' => 'required|string',
            'tipo'      => 'required|string|in:admin,cocina,pedido,caja',
        ]);
        $u = Usuario::find($id);
        if (!$u) return response()->json(['success' => false, 'error' => 'No encontrado'], 404);

        $u->usuario   = $request->usuario;
        $u->nombres   = $request->nombres;
        $u->apellidos = $request->apellidos;
        $u->tipo      = $request->tipo;
        if ($request->filled('clave')) {
            $u->clave = \Illuminate\Support\Facades\Hash::make($request->clave);
        }
        $u->save();
        return response()->json(['success' => true]);
    }

    public function eliminarUsuario($id)
    {
        if ((int) $id === 1) return response()->json(['success' => false, 'error' => 'Usuario protegido'], 403);
        $u = Usuario::find($id);
        if (!$u) return response()->json(['success' => false, 'error' => 'No encontrado'], 404);
        $u->delete();
        return response()->json(['success' => true]);
    }

    // ─────────────────────────────────────────────────────────
    //  PEDIDOS
    // ─────────────────────────────────────────────────────────

    public function recientes()
    {
        return response()->json(
            Pedido::orderBy('fecha', 'desc')->limit(10)->get()->map(fn($p) => [
                'id'      => (int)    $p->id,
                'cliente' => 'Mesa ' . $p->mesa,
                'fecha'   => (string) $p->fecha,
                'total'   => null,
                'estado'  => (string) $p->estado,
                'costo'   => $p->calcularTotalCosto(),
                'detalle' => (string) $p->detalle,
            ])
        );
    }

    // ─────────────────────────────────────────────────────────
    //  GRÁFICAS LEGACY (compatibilidad)
    // ─────────────────────────────────────────────────────────

    public function pagosPorMetodo()  { return response()->json((new PagosPorMetodoGrafica())->ejecutar()); }
    public function ventasDiarias()   { return response()->json((new VentasDiariasGrafica())->ejecutar()); }
    public function ventasMensuales() { return response()->json((new VentasMensualesGrafica())->ejecutar()); }
    public function ventasAnuales()   { return response()->json((new VentasAnualesGrafica())->ejecutar()); }
    public function pedidosDiarios()  { return response()->json((new PedidosDiariosGrafica())->ejecutar()); }
    public function pedidosMensuales(){ return response()->json((new PedidosMensualesGrafica())->ejecutar()); }
    public function pedidosAnuales()  { return response()->json((new PedidosAnualesGrafica())->ejecutar()); }

    // ─────────────────────────────────────────────────────────
    //  REPORTES
    // ─────────────────────────────────────────────────────────

    public function reportePedidos(Request $request)
    {
        $query = Pedido::query()->leftJoin('usuarios', 'usuarios.id', '=', 'pedido.usuario_id');
        if ($request->filled('from')) $query->whereDate('pedido.fecha', '>=', $request->input('from'));
        if ($request->filled('to'))   $query->whereDate('pedido.fecha', '<=', $request->input('to'));
        if ($request->filled('mesa')) $query->where('pedido.mesa', (int) $request->input('mesa'));

        return response()->json(
            $query->orderBy('pedido.fecha', 'desc')
                ->select('pedido.*', DB::raw("CONCAT(usuarios.nombres, ' ', usuarios.apellidos) as mesero"))
                ->get()
                ->map(fn($p) => [
                    'id'           => (int)    $p->id,
                    'mesa'         => (int)    $p->mesa,
                    'mesero'       => $p->mesero ? (string) $p->mesero : null,
                    'tipo_servicio'=> (string) $p->tipo_servicio,
                    'detalle'      => (string) $p->detalle,
                    'estado'       => (string) $p->estado,
                    'fecha'        => (string) $p->fecha,
                    'costo'        => $p->calcularTotalCosto(),
                ])
                ->filter(function ($row) use ($request) {
                    $min = $request->filled('costo_min') ? (float) $request->input('costo_min') : null;
                    $max = $request->filled('costo_max') ? (float) $request->input('costo_max') : null;
                    if ($min !== null && $row['costo'] < $min) return false;
                    if ($max !== null && $row['costo'] > $max) return false;
                    return true;
                })->values()
        );
    }

    public function reportePedidosPorMesero(Request $request)
    {
        $query = Pedido::query()->leftJoin('usuarios', 'usuarios.id', '=', 'pedido.usuario_id');
        if ($request->filled('from'))      $query->whereDate('pedido.fecha', '>=', $request->input('from'));
        if ($request->filled('to'))        $query->whereDate('pedido.fecha', '<=', $request->input('to'));
        if ($request->filled('mesero_id')) $query->where('pedido.usuario_id', (int) $request->input('mesero_id'));

        return response()->json(
            $query->orderBy('pedido.fecha', 'desc')
                ->select('pedido.*', DB::raw("CONCAT(usuarios.nombres, ' ', usuarios.apellidos) as mesero"))
                ->get()->map(fn($p) => [
                    'id'      => (int)    $p->id,
                    'mesa'    => (int)    $p->mesa,
                    'mesero'  => $p->mesero ? (string) $p->mesero : null,
                    'detalle' => (string) $p->detalle,
                    'estado'  => (string) $p->estado,
                    'fecha'   => (string) $p->fecha,
                    'costo'   => $p->calcularTotalCosto(),
                ])
        );
    }

    public function reporteRecibosEntregados(Request $request)
    {
        $query = Recibo::query()->leftJoin('venta', 'venta.id', '=', 'recibo.venta_id');
        if ($request->filled('from'))      $query->whereDate('recibo.fecha', '>=', $request->input('from'));
        if ($request->filled('to'))        $query->whereDate('recibo.fecha', '<=', $request->input('to'));
        if ($request->filled('tipo'))      $query->where('recibo.tipo', $request->input('tipo'));
        if ($request->filled('numero'))    $query->where('recibo.numero', 'like', '%' . $request->input('numero') . '%');
        if ($request->filled('costo_min')) $query->where('recibo.total', '>=', (float) $request->input('costo_min'));
        if ($request->filled('costo_max')) $query->where('recibo.total', '<=', (float) $request->input('costo_max'));
        if ($request->filled('mesa'))      $query->whereHas('venta.pedidos', fn($q) => $q->where('mesa', $request->input('mesa')));

        return response()->json(
            $query->orderBy('recibo.fecha', 'desc')
                ->select('recibo.*', 'venta.monto as venta_monto', 'venta.fecha as venta_fecha', 'venta.metodo_pago')
                ->distinct()->get()
                ->map(function ($r) {
                    $pedidos = Pedido::where('venta_id', $r->venta_id)->get();
                    return [
                        'id'          => (int)    $r->id,
                        'numero'      => (string) $r->numero,
                        'fecha'       => (string) $r->fecha,
                        'tipo'        => (string) $r->tipo,
                        'estado_sunat'=> (string) $r->estado_sunat,
                        'subtotal'    => (float)  $r->subtotal,
                        'igv'         => (float)  $r->igv,
                        'total'       => (float)  $r->total,
                        'venta_id'    => (int)    $r->venta_id,
                        'venta_monto' => $r->venta_monto !== null ? (float) $r->venta_monto : null,
                        'venta_fecha' => $r->venta_fecha ? (string) $r->venta_fecha : null,
                        'metodo_pago' => (string) $r->metodo_pago,
                        'mesa'        => $pedidos->pluck('mesa')->unique()->implode(', '),
                        'detalle'     => $pedidos->pluck('detalle')->implode('; '),
                    ];
                })
        );
    }

    // ─────────────────────────────────────────────────────────
    //  CAJA / CONFIGURACION
    // ─────────────────────────────────────────────────────────

    public function cajaConfig()
    {
        $data = Configuracion::obtenerTodas();
        if (empty($data)) {
            $defaults = [
                'nombre_comercial' => env('CAJA_NOMBRE_COMERCIAL', 'El Hornero'),
                'ruc'              => env('CAJA_RUC',              ''),
                'direccion'        => env('CAJA_DIRECCION',        ''),
                'telefono'         => env('CAJA_TELEFONO',         ''),
                'yape_numero'      => env('CAJA_YAPE_NUMERO',      ''),
            ];
            foreach ($defaults as $k => $v) Configuracion::establecer($k, $v);
            $data = $defaults;
        }
        return response()->json($data);
    }

    public function updateCajaConfig(Request $request)
    {
        foreach (['nombre_comercial', 'ruc', 'direccion', 'telefono', 'yape_numero'] as $campo) {
            if ($request->has($campo)) Configuracion::establecer($campo, $request->input($campo));
        }
        if ($request->hasFile('qr')) {
            $dir = public_path('images/qr');
            File::ensureDirectoryExists($dir);
            $request->file('qr')->move($dir, 'yape.png');
        }
        return response()->json(['success' => true, 'config' => Configuracion::obtenerTodas()]);
    }
}
