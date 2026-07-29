<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Usuario;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'usuario' => 'required|string',
            'clave' => 'required|string',
        ]);

        $user = Usuario::where('usuario', $request->usuario)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 401);
        }

        if (!\Illuminate\Support\Facades\Hash::check($request->clave, $user->clave)) {
            return response()->json([
                'success' => false,
                'message' => 'Contraseña incorrecta.',
            ], 401);
        }

        $tipoRaw = strtolower((string) $user->tipo);
        if (in_array($tipoRaw, ['admin'])) {
            $tipoFront = 'admin';
        } elseif (in_array($tipoRaw, ['caja', 'encargado', 'encargado_caja'])) {
            $tipoFront = 'caja';
        } elseif (in_array($tipoRaw, ['cocina', 'kitchen'])) {
            $tipoFront = 'cocina';
        } elseif (in_array($tipoRaw, ['pedido', 'mozo'])) {
            $tipoFront = 'pedido';
        } else {
            $tipoFront = 'menu';
        }

        $user->tokens()->delete();

        $token = $user->createToken('auth')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'tipo' => $tipoFront,
            'id' => (int) $user->id,
            'usuario' => (string) $user->usuario,
            'nombres' => (string) ($user->nombres ?? ''),
            'apellidos' => (string) ($user->apellidos ?? ''),
        ]);
    }

    public function logout(Request $request)
    {
        // Revoke the current user's token
        $request->user()->currentAccessToken()->delete();
        
        return response()->json([
            'success' => true,
        ]);
    }
}
