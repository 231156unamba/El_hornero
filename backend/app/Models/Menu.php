<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use HasFactory;

    protected $table = 'menu';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'precio',
        'descripcion',
        'imagen',
        'categoria',
        'discount_percentage',
        'discount_expires_at',
    ];
    
    protected $casts = [
        'discount_expires_at' => 'datetime',
    ];
}
