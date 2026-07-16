<?php

namespace App\Services\Graficas;

use Illuminate\Support\Facades\DB;

abstract class Grafica
{
    protected string $tipoGrafica;
    protected string $modelo;

    abstract public function obtenerDatos(): array;

    public function getTipoGrafica(): string
    {
        return $this->tipoGrafica;
    }

    public function getModelo(): string
    {
        return $this->modelo;
    }

    protected function formatearDatos($rows): array
    {
        return $rows->map(function ($row) {
            return [
                'label' => (string) $row->label,
                'value' => (float) $row->value,
            ];
        })->toArray();
    }

    public function ejecutar(): array
    {
        return $this->obtenerDatos();
    }
}
