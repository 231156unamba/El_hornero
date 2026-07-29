<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Model
{
    use HasFactory, HasApiTokens;

    protected $table = 'usuarios';
    public $timestamps = false; // Assuming no created_at/updated_at columns based on typical raw PHP projects

    protected $fillable = [
        'usuario',
        'nombres',
        'apellidos',
        'clave',
        'tipo',
    ];

    protected $hidden = [
        'clave',
    ];
}
