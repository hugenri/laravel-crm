<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Catálogos de los desplegables de clientes, responsables y oportunidades.
 *
 * Las mismas listas en el formulario de actividad y en el filtro del
 * calendario, para que el filtro no ofrezca un cliente que el formulario no
 * deja elegir ni uno de los dos se recargue con otro nombre.
 *
 * Solo lectura: no aplican permisos ni filtran por usuario. Quien ve la
 * pantalla ya tiene el permiso de consulta del módulo, y el backend vuelve a
 * filtrar lo que corresponda.
 */
final class SelectOptions
{
    /**
     * Clientes elegibles: los activos. En edición se puede incluir el actual
     * aunque esté inactivo, o la ficha no mostraría cuál era. Orden por
     * nombre.
     *
     * @return Collection<int, Client>
     */
    public static function clients(?Client $include = null): Collection
    {
        $clients = Client::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($include === null) {
            return $clients;
        }

        return $clients->push($include->only('id', 'name'))->unique('id')->values();
    }

    /**
     * Responsables activos: a una cuenta desactivada no se le asigna trabajo
     * nuevo, aunque conserve actividades históricas.
     *
     * @return Collection<int, User>
     */
    public static function users(): Collection
    {
        return User::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Oportunidades con las columnas que el formulario y los filtros
     * necesitan. Van todas y Vue las agrupa por cliente; que la oportunidad
     * pertenezca al cliente se comprueba en el backend.
     *
     * @return Collection<int, Opportunity>
     */
    public static function opportunities(): Collection
    {
        return Opportunity::query()
            ->orderBy('name')
            ->get(['id', 'client_id', 'name']);
    }
}
