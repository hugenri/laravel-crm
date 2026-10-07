<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Permiso de la aplicación.
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 */
class Permission extends SpatiePermission
{
    /**
     * Etiqueta de config('roles.permissions'), para no duplicar los textos en
     * Vue. El catálogo se indexa en PHP porque los nombres llevan puntos
     * ("users.view") y la notación de Laravel los vería como anidamiento.
     */
    public function label(): string
    {
        $catalog = config('roles.permissions', []);

        return $catalog[$this->name]['label'] ?? $this->name;
    }

    /**
     * Grupo funcional al que pertenece el permiso, usado para agrupar la
     * matriz de permisos en la pantalla de roles.
     */
    public function group(): string
    {
        $catalog = config('roles.permissions', []);

        return $catalog[$this->name]['group'] ?? __('roles.other_group');
    }
}
