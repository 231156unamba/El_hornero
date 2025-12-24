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

        // Note: Using plain text comparison as per original legacy code.
        // It is highly recommended to migrate to bcrypt hashing.
        $user = Usuario::where('usuario', $request->usuario)
                       ->where('clave', $request->clave)
                       ->first();

        if ($user) {
            // Store in session if needed, but for API usually we return token or just user info.
            // Original code used session_start() and returned JSON.
            // Since we are migrating to an API for frontend, we'll return the JSON response.
            
            // If the frontend expects a session, we might need to use Laravel's session driver
            // or Sanctum for token authentication.
            // For now, I will replicate the JSON response.
            
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

            return response()->json([
                'success' => true,
                'tipo' => $tipoFront,
                'id' => (int) $user->id,
                'usuario' => (string) $user->usuario,
            ]);
        }

        return response()->json([
            'success' => false,
        ]);
    }
}
