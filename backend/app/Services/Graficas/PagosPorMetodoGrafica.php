<?php

namespace App\Services\Graficas;

use App\Models\Venta;
use Illuminate\Support\Facades\DB;

class PagosPorMetodoGrafica extends Grafica
{
    protected string $tipoGrafica = 'pie';
    protected string $modelo = Venta::class;

    public function obtenerDatos(): array
    {
        $rows = Venta::query()
            ->select('metodo_pago as label', DB::raw('SUM(monto) as value'))
            ->whereNotNull('metodo_pago')
            ->where('metodo_pago', '<>', '')
            ->groupBy('metodo_pago')
            ->orderByDesc('value')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $data = $rows->map(function ($row) {
            $label = trim((string) $row->label);
            return [
                'label' => $label !== '' ? $label : 'Sin dato',
                'value' => (float) $row->value,
            ];
        })->values();

        return $data->toArray();
    }
}
