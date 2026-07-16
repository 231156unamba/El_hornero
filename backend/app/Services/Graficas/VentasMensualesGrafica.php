<?php

namespace App\Services\Graficas;

use App\Models\Venta;
use Illuminate\Support\Facades\DB;

class VentasMensualesGrafica extends Grafica
{
    protected string $tipoGrafica = 'bar';
    protected string $modelo = Venta::class;

    public function obtenerDatos(): array
    {
        $rows = Venta::select(DB::raw("DATE_FORMAT(fecha, '%Y-%m') as label"), DB::raw('SUM(monto) as value'))
            ->groupBy('label')
            ->orderBy('label')
            ->get();

        return $this->formatearDatos($rows);
    }
}
