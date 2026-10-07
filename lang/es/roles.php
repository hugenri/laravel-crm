<?php

return [

    'role_name' => 'Nombre del rol',

    // Grupo de reserva para un permiso que se añadiera a la base de datos sin
    // pasar por config/roles.php.
    'other_group' => 'Otros',

    'created' => 'Rol :name creado correctamente.',
    'updated' => 'Rol :name actualizado correctamente.',
    'deleted' => 'Rol :name eliminado correctamente.',

    'invalid_role' => 'El rol seleccionado no existe.',

    'system_role_protected' => 'Es un rol del sistema y no se puede eliminar.',
    'system_role_name_locked' => 'El nombre de un rol del sistema no se puede cambiar.',
    'role_in_use' => 'No se puede eliminar un rol que tiene usuarios asignados.',

];
