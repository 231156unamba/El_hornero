<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\ReciboController;
use App\Http\Controllers\PedidoController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);
Route::get('/menu', [MenuController::class, 'index']);

Route::prefix('caja')->group(function () {
    Route::post('/abrir', [CajaController::class, 'abrir']);
    Route::post('/cerrar', [CajaController::class, 'cerrar']);
    Route::get('/estado', [CajaController::class, 'estado']);
    Route::post('/venta', [CajaController::class, 'registrarVenta']);
    Route::post('/recibo', [ReciboController::class, 'generar']);
    Route::post('/sunat', [ReciboController::class, 'enviarSunat']);
});

Route::get('/pedidos', [PedidoController::class, 'index']);
Route::post('/pedidos', [PedidoController::class, 'store']);
Route::post('/pedidos/actualizar', [PedidoController::class, 'updateStatusFromPost']);
