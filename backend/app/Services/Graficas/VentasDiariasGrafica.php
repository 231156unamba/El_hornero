<?php

namespace App\Services\Graficas;

use App\Models\Venta;
use Illuminate\Support\Facades\DB;

class VentasDiariasGrafica extends Grafica
{
    protected string $tipoGrafica = 'line';
    protected string $modelo = Venta::class;

    public function obtenerDatos(): array
    {
        $rows = Venta::select(DB::raw('DATE(fecha) as label'), DB::raw('SUM(monto) as value'))
            ->groupBy('label')
            ->orderBy('label')
            ->get();

        return $this->formatearDatos($rows);
    }
}
