<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SunatLog extends Model
{
    use HasFactory;

    protected $table = 'sunat_log';
    public $timestamps = false;

    protected $fillable = [
        'recibo_id',
        'respuesta',
    ];
}
