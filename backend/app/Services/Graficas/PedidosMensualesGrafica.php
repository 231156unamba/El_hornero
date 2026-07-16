<?php

namespace App\Services\Graficas;

use App\Models\Pedido;
use Illuminate\Support\Facades\DB;

class PedidosMensualesGrafica extends Grafica
{
    protected string $tipoGrafica = 'bar';
    protected string $modelo = Pedido::class;

    public function obtenerDatos(): array
    {
        $rows = Pedido::select(DB::raw("DATE_FORMAT(fecha, '%Y-%m') as label"), DB::raw('COUNT(*) as value'))
            ->groupBy('label')
            ->orderBy('label')
            ->get();

        return $this->formatearDatos($rows);
    }
}
