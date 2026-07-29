<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\ReciboController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\AdminController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ── Auth ──────────────────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// ── Menú público ─────────────────────────────────────────────
Route::get('/menu', [MenuController::class, 'index']);
Route::post('/menu', [MenuController::class, 'store']);
Route::post('/menu/{id}', [MenuController::class, 'update']);   // method spoofing _method=PUT
Route::put('/menu/{id}', [MenuController::class, 'update']);
Route::delete('/menu/{id}', [MenuController::class, 'destroy']);
Route::patch('/menu/{id}/toggle', [MenuController::class, 'toggle']);

// ── Rutas Privadas (Requieren Autenticación) ──────────────────
Route::middleware('auth:sanctum')->group(function () {
    
    // ── Caja (módulo operativo) ───────────────────────────────────
    Route::prefix('caja')->group(function () {
        Route::post('/abrir',  [CajaController::class,  'abrir']);
        Route::post('/cerrar', [CajaController::class,  'cerrar']);
        Route::get('/estado',  [CajaController::class,  'estado']);
        Route::post('/venta',  [CajaController::class,  'registrarVenta']);
        Route::post('/recibo', [ReciboController::class, 'generar']);
    });

    // ── Pedidos ───────────────────────────────────────────────────
    Route::get('/pedidos',            [PedidoController::class, 'index']);
    Route::get('/pedidos/mesas-ocupadas', [PedidoController::class, 'mesasOcupadas']);
    Route::post('/pedidos',           [PedidoController::class, 'store']);
    Route::post('/pedidos/actualizar',[PedidoController::class, 'updateStatusFromPost']);
    Route::delete('/pedidos/{id}',    [PedidoController::class, 'destroy']);

    // ── Admin ─────────────────────────────────────────────────────
    Route::prefix('admin')->group(function () {

        // Dashboard (endpoint unificado — 1 sola llamada)
        Route::get('/dashboard-data', [AdminController::class, 'dashboardData']);

        // Stats legacy (usado por módulos existentes)
        Route::get('/stats',           [AdminController::class, 'stats']);
        Route::get('/recientes',       [AdminController::class, 'recientes']);
        Route::get('/pagos-por-metodo',[AdminController::class, 'pagosPorMetodo']);

        // Gráficas individuales legacy (compatibilidad)
        Route::get('/ventas-diarias',   [AdminController::class, 'ventasDiarias']);
        Route::get('/ventas-mensuales', [AdminController::class, 'ventasMensuales']);
        Route::get('/ventas-anuales',   [AdminController::class, 'ventasAnuales']);
        Route::get('/pedidos-diarios',  [AdminController::class, 'pedidosDiarios']);
        Route::get('/pedidos-mensuales',[AdminController::class, 'pedidosMensuales']);
        Route::get('/pedidos-anuales',  [AdminController::class, 'pedidosAnuales']);

        // Usuarios CRUD
        Route::get('/usuarios',          [AdminController::class, 'clientes']);
        Route::get('/clientes',          [AdminController::class, 'clientes']);
        Route::post('/usuarios',         [AdminController::class, 'crearUsuario']);
        Route::put('/usuarios/{id}',     [AdminController::class, 'actualizarUsuario']);
        Route::delete('/usuarios/{id}',  [AdminController::class, 'eliminarUsuario']);

        // Reportes
        Route::get('/reportes/pedidos',           [AdminController::class, 'reportePedidos']);
        Route::get('/reportes/pedidos-mesero',    [AdminController::class, 'reportePedidosPorMesero']);
        Route::get('/reportes/recibos-entregados',[AdminController::class, 'reporteRecibosEntregados']);

        // Configuración de caja
        Route::get('/caja/config',  [AdminController::class, 'cajaConfig']);
        Route::post('/caja/config', [AdminController::class, 'updateCajaConfig']);
    });
});
