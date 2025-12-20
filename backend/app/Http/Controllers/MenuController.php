<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Menu;

class MenuController extends Controller
{
    public function index()
    {
        try {
            $menu = Menu::orderBy('id')->get();
            
            // Map to ensure types if necessary, though Eloquent handles casting if defined in Model.
            // Original code did explicit casting.
            $data = $menu->map(function ($item) {
                return [
                    'id' => (int) $item->id,
                    'nombre' => (string) $item->nombre,
                    'precio' => (float) $item->precio,
                    'descripcion' => (string) $item->descripcion,
                    'imagen' => (string) $item->imagen,
                ];
            });

            return response()->json($data);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Excepción: ' . $e->getMessage()], 500);
        }
    }
}
