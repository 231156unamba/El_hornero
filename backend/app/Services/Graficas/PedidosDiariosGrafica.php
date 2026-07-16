<?php

namespace App\Services\Graficas;

use App\Models\Pedido;
use Illuminate\Support\Facades\DB;

class PedidosDiariosGrafica extends Grafica
{
    protected string $tipoGrafica = 'line';
    protected string $modelo = Pedido::class;

    public function obtenerDatos(): array
    {
        $rows = Pedido::select(DB::raw('DATE(fecha) as label'), DB::raw('COUNT(*) as value'))
            ->groupBy('label')
            ->orderBy('label')
            ->get();

        return $this->formatearDatos($rows);
    }
}
