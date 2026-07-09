<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    use HasFactory;

    protected $table = 'configuracion';
    protected $fillable = ['clave', 'valor'];

    // Método para obtener todas las configuraciones como array clave-valor
    public static function obtenerTodas()
    {
        return self::all()->pluck('valor', 'clave')->toArray();
    }

    // Método para obtener una configuración específica
    public static function obtener($clave, $default = null)
    {
        $config = self::where('clave', $clave)->first();
        return $config ? $config->valor : $default;
    }

    // Método para establecer una configuración
    public static function establecer($clave, $valor)
    {
        return self::updateOrCreate(
            ['clave' => $clave],
            ['valor' => $valor]
        );
    }
}
