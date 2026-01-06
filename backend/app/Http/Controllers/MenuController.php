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
            $data = $menu->map(function ($item) {
                return [
                    'id' => (int) $item->id,
                    'nombre' => (string) $item->nombre,
                    'precio' => (float) $item->precio,
                    'descripcion' => (string) $item->descripcion,
                    'imagen' => $item->imagen ? (string) url($item->imagen) : null,
                    'categoria' => (string) $item->categoria,
                ];
            });

            return response()->json($data);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Excepción: ' . $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string',
            'precio' => 'required|numeric|min:0',
            'descripcion' => 'required|string',
            'imagen' => 'required|file|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'categoria' => 'required|string|in:bebidas,comida',
        ]);
        $menu = new Menu();
        $menu->nombre = $request->nombre;
        $menu->precio = $request->precio;
        $menu->descripcion = $request->descripcion;
        if ($request->hasFile('imagen')) {
            $file = $request->file('imagen');
            $dir = public_path('images/menu');
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $original = $file->getClientOriginalName();
            $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9_\\.-]/', '_', $original);
            $file->move($dir, $safeName);
            $menu->imagen = '/images/menu/' . $safeName;
        }
        $menu->categoria = $request->categoria;
        $menu->save();

        return response()->json(['success' => true, 'id' => $menu->id]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string',
            'precio' => 'required|numeric|min:0',
            'descripcion' => 'required|string',
            'categoria' => 'required|string|in:bebidas,comida',
        ]);
        $menu = Menu::find($id);
        if (!$menu) {
            return response()->json(['success' => false, 'error' => 'No encontrado'], 404);
        }
        $menu->nombre = $request->nombre;
        $menu->precio = $request->precio;
        $menu->descripcion = $request->descripcion;
        if ($request->hasFile('imagen')) {
            $file = $request->file('imagen');
            $dir = public_path('images/menu');
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            $original = $file->getClientOriginalName();
            $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9_\\.-]/', '_', $original);
            $file->move($dir, $safeName);
            $menu->imagen = '/images/menu/' . $safeName;
        }
        $menu->categoria = $request->categoria;
        $menu->save();
        return response()->json(['success' => true]);
    }

    public function destroy($id)
    {
        $menu = Menu::find($id);
        if (!$menu) {
            return response()->json(['success' => false, 'error' => 'No encontrado'], 404);
        }
        $menu->delete();
        return response()->json(['success' => true]);
    }
}
