<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Menu;
use Illuminate\Support\Str;

class MenuController extends Controller
{
    public function __construct()
    {
        $dir = public_path('images/menu');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    private function menuImageUrlFromName(Request $request, ?string $imageName): ?string
    {
        if (!$imageName) {
            return null;
        }
        if (preg_match('/^https?:\\/\\//i', $imageName)) {
            return $imageName;
        }
        $name = basename($imageName);
        return $request->getSchemeAndHttpHost() . '/images/menu/' . $name;
    }

    private function storeMenuImage(Request $request, string $dishName): ?string
    {
        if (!$request->hasFile('imagen')) {
            return null;
        }
        $file = $request->file('imagen');
        if (!$file) {
            return null;
        }

        $dir = public_path('images/menu');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        if (!is_writable($dir)) {
            return null;
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        $slug = Str::slug($dishName);
        $random = bin2hex(random_bytes(6));
        $timestamp = now()->format('YmdHis');
        $safeName = ($slug ? $slug : 'plato') . '_' . $timestamp . '_' . $random . ($ext ? '.' . $ext : '');

        try {
            $file->move($dir, $safeName);
        } catch (\Throwable $e) {
            return null;
        }

        return $safeName;
    }

    public function index(Request $request)
    {
        try {
            $menu = Menu::orderBy('id')->get();
            $data = $menu->map(function ($item) use ($request) {
                return [
                    'id' => (int) $item->id,
                    'nombre' => (string) $item->nombre,
                    'precio' => (float) $item->precio,
                    'descripcion' => (string) $item->descripcion,
                    'imagen' => $item->imagen ? basename((string) $item->imagen) : null,
                    'imagen_url' => $this->menuImageUrlFromName($request, $item->imagen ? (string) $item->imagen : null),
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
        if (!$request->hasFile('imagen')) {
            return response()->json(['success' => false, 'error' => 'Archivo de imagen no recibido'], 422);
        }
        $menu = new Menu();
        $menu->nombre = $request->nombre;
        $menu->precio = $request->precio;
        $menu->descripcion = $request->descripcion;
        $menu->imagen = $this->storeMenuImage($request, (string) $request->nombre);
        if (!$menu->imagen) {
            return response()->json(['success' => false, 'error' => 'No se pudo guardar la imagen en el servidor'], 500);
        }
        $menu->categoria = $request->categoria;
        $menu->save();

        return response()->json(['success' => true, 'id' => $menu->id, 'imagen' => $menu->imagen]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string',
            'precio' => 'required|numeric|min:0',
            'descripcion' => 'required|string',
            'categoria' => 'required|string|in:bebidas,comida',
            'imagen' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp|max:4096',
        ]);
        $menu = Menu::find($id);
        if (!$menu) {
            return response()->json(['success' => false, 'error' => 'No encontrado'], 404);
        }
        $menu->nombre = $request->nombre;
        $menu->precio = $request->precio;
        $menu->descripcion = $request->descripcion;
        $newImage = $this->storeMenuImage($request, (string) $request->nombre);
        if ($request->hasFile('imagen') && !$newImage) {
            return response()->json(['success' => false, 'error' => 'No se pudo guardar la imagen en el servidor'], 500);
        }
        if ($newImage) {
            $menu->imagen = $newImage;
        }
        $menu->categoria = $request->categoria;
        $menu->save();
        return response()->json(['success' => true, 'imagen' => $menu->imagen]);
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
