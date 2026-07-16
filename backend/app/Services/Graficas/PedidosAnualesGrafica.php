<?php

namespace App\Services\Graficas;

use App\Models\Pedido;
use Illuminate\Support\Facades\DB;

class PedidosAnualesGrafica extends Grafica
{
    protected string $tipoGrafica = 'bar';
    protected string $modelo = Pedido::class;

    public function obtenerDatos(): array
    {
        $rows = Pedido::select(DB::raw('YEAR(fecha) as label'), DB::raw('COUNT(*) as value'))
            ->groupBy('label')
            ->orderBy('label')
            ->get();

        return $this->formatearDatos($rows);
    }
}
