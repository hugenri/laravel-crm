<?php

namespace App\Support\Reports;

use App\Models\User;

/**
 * Nombre de cada responsable, indexado por identificador.
 *
 * Clase propia porque los tres reportes por responsable la necesitan idéntica,
 * y porque consultar todos los usuarios —no solo los activos— es una decisión
 * de la pantalla, no de cada reporte.
 *
 * Un reporte tiene que nombrar a quien vendió o capturó en el periodo y luego
 * fue desactivado, que es justo el caso que un filtro de octubre tiene que
 * seguir mostrando. El catálogo de activos de App\Support\SelectOptions sigue
 * siendo el del filtro, donde sí conviene no ofrecer cuentas desactivadas.
 */
final class ResponsibleNames
{
    /**
     * Todos los nombres indexados por identificador de usuario.
     *
     * @return array<int, string>
     */
    public static function map(): array
    {
        return User::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
