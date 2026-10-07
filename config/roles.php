<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Roles del sistema
    |--------------------------------------------------------------------------
    |
    | Roles que el producto crea y mantiene. No se pueden eliminar mientras
    | existan: un usuario que los pierda se quedaría sin permisos y sin forma
    | de recuperarlos desde la interfaz.
    |
    */

    'system_roles' => [
        'Administrador',
        'Gerente',
        'Vendedor',
    ],

    /*
    |--------------------------------------------------------------------------
    | Catálogo de permisos
    |--------------------------------------------------------------------------
    |
    | Única fuente de verdad de los permisos del proyecto. El label y el grupo
    | presentan la matriz en la interfaz; la clave es exactamente el nombre
    | que se crea en la tabla "permissions". Los permisos de un módulo nuevo
    | se añaden aquí y el resto del sistema los reconoce sin cambios: la
    | autorización lee el Gate y la interfaz recibe la lista del usuario.
    |
    */

    'permissions' => [

        // --- Usuarios ---
        'users.view' => ['group' => 'Usuarios', 'label' => 'Ver usuarios'],
        'users.create' => ['group' => 'Usuarios', 'label' => 'Crear usuarios'],
        'users.update' => ['group' => 'Usuarios', 'label' => 'Editar usuarios'],
        'users.delete' => ['group' => 'Usuarios', 'label' => 'Eliminar usuarios'],
        'users.change_status' => ['group' => 'Usuarios', 'label' => 'Activar o desactivar usuarios'],
        'users.assign_roles' => ['group' => 'Usuarios', 'label' => 'Asignar roles a usuarios'],

        // --- Roles y permisos ---
        'roles.view' => ['group' => 'Roles y permisos', 'label' => 'Ver roles y permisos'],
        'roles.create' => ['group' => 'Roles y permisos', 'label' => 'Crear roles'],
        'roles.update' => ['group' => 'Roles y permisos', 'label' => 'Editar roles y sus permisos'],
        'roles.delete' => ['group' => 'Roles y permisos', 'label' => 'Eliminar roles'],

        // --- Clientes ---
        'clients.view' => ['group' => 'Clientes', 'label' => 'Ver clientes'],
        'clients.create' => ['group' => 'Clientes', 'label' => 'Crear clientes'],
        'clients.update' => ['group' => 'Clientes', 'label' => 'Editar clientes'],
        'clients.delete' => ['group' => 'Clientes', 'label' => 'Eliminar clientes'],
        'clients.change_status' => ['group' => 'Clientes', 'label' => 'Activar o desactivar clientes'],

        // --- Contactos ---
        'contacts.view' => ['group' => 'Contactos', 'label' => 'Ver contactos'],
        'contacts.create' => ['group' => 'Contactos', 'label' => 'Crear contactos'],
        'contacts.update' => ['group' => 'Contactos', 'label' => 'Editar contactos'],
        'contacts.delete' => ['group' => 'Contactos', 'label' => 'Eliminar contactos'],
        'contacts.set_primary' => ['group' => 'Contactos', 'label' => 'Establecer el contacto principal'],

        // --- Oportunidades ---
        'opportunities.view' => ['group' => 'Oportunidades', 'label' => 'Ver oportunidades'],
        'opportunities.create' => ['group' => 'Oportunidades', 'label' => 'Crear oportunidades'],
        'opportunities.update' => ['group' => 'Oportunidades', 'label' => 'Editar oportunidades'],
        'opportunities.delete' => ['group' => 'Oportunidades', 'label' => 'Eliminar oportunidades'],
        'opportunities.change_status' => ['group' => 'Oportunidades', 'label' => 'Cerrar o reabrir oportunidades'],

        // --- Actividades ---
        'activities.view' => ['group' => 'Actividades', 'label' => 'Ver actividades'],
        'activities.create' => ['group' => 'Actividades', 'label' => 'Crear actividades'],
        'activities.update' => ['group' => 'Actividades', 'label' => 'Editar actividades'],
        'activities.delete' => ['group' => 'Actividades', 'label' => 'Eliminar actividades'],

        // --- Prospectos ---
        'prospects.view' => ['group' => 'Prospectos', 'label' => 'Ver prospectos'],
        'prospects.create' => ['group' => 'Prospectos', 'label' => 'Crear prospectos'],
        'prospects.update' => ['group' => 'Prospectos', 'label' => 'Editar prospectos'],
        'prospects.delete' => ['group' => 'Prospectos', 'label' => 'Eliminar prospectos'],

        // Dos permisos más que los cuatro básicos, por la misma razón que
        // clients.change_status y opportunities.change_status: son
        // decisiones comerciales, no un efecto secundario de editar un texto.
        //
        // Calificar o descartar un prospecto decide si el embudo sigue
        // siendo trabajo, así que va aparte de prospects.update, igual que
        // activar un cliente.
        'prospects.change_status' => ['group' => 'Prospectos', 'label' => 'Cambiar el estado del prospecto'],

        // La conversion es la unica accion irreversible del modulo: crea un
        // cliente y un contacto y no se puede deshacer. Mezclarla con
        // prospects.update permitiria generar registros de la cartera sin haber
        // revisado los datos que se van a escribir, asi que exige su propio
        // permiso.
        'prospects.convert' => ['group' => 'Prospectos', 'label' => 'Convertir prospectos a clientes'],

        // --- Cotizaciones ---
        'quotes.view' => ['group' => 'Cotizaciones', 'label' => 'Ver cotizaciones'],
        'quotes.create' => ['group' => 'Cotizaciones', 'label' => 'Crear cotizaciones'],
        'quotes.update' => ['group' => 'Cotizaciones', 'label' => 'Editar cotizaciones'],
        'quotes.delete' => ['group' => 'Cotizaciones', 'label' => 'Eliminar cotizaciones'],
        'quotes.change_status' => ['group' => 'Cotizaciones', 'label' => 'Cambiar el estado de una cotización'],
        'quotes.pdf' => ['group' => 'Cotizaciones', 'label' => 'Generar el PDF de una cotización'],

        /*
         * Reportes necesita un permiso propio y no reusa el de ningún módulo,
         * al contrario que el calendario: este dibuja solo actividades, así
         * que activities.view abre exactamente la misma vista. Reportes
         * agrupa cuatro módulos distintos y su visibilidad tiene que poder
         * decidirse por separado.
         *
         * Es el único permiso del módulo porque la pantalla no escribe nada.
         * Lo que cada bloque muestre se resuelve con el permiso de consulta
         * del módulo que agrega, así que reports.view nunca revela más que
         * el listado correspondiente.
         */
        'reports.view' => ['group' => 'Reportes', 'label' => 'Ver reportes'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Matriz de permisos por rol
    |--------------------------------------------------------------------------
    |
    | Define qué permisos recibe cada rol creado por el seeder. El asterisco
    | significa "todos los permisos del catálogo". Los roles creados después
    | desde la interfaz no aparecen aquí: sus permisos se guardan en la base
    | de datos y se administran desde el módulo de roles.
    |
    | Criterio: cada rol recibe lo que necesita para trabajar y el borrado
    | queda en quien responde por el dato. Excepción: activities.delete
    | también se le da al gerente, porque la especificación de la fase 5 lo
    | pide explícitamente para poder depurar el seguimiento registrado.
    |
    */

    'matrix' => [

        'Administrador' => ['*'],

        'Gerente' => [
            'users.view',
            'users.create',
            'users.update',
            'users.change_status',

            'clients.view',
            'clients.create',
            'clients.update',
            'clients.change_status',

            'contacts.view',
            'contacts.create',
            'contacts.update',
            'contacts.set_primary',

            'opportunities.view',
            'opportunities.create',
            'opportunities.update',
            'opportunities.change_status',

            'activities.view',
            'activities.create',
            'activities.update',
            'activities.delete',

            // El gerente supervisa el embudo igual que la cartera. No borra
            // prospectos por el mismo criterio que no borra clientes: eliminar
            // un prospecto destruye el registro de quien se contacto y por que
            // no avanzo, que es informacion de la que la gerencia responde.
            'prospects.view',
            'prospects.create',
            'prospects.update',
            'prospects.change_status',
            'prospects.convert',

            // El gerente supervisa la propuesta comercial igual que el resto del
            // embudo: la prepara, decide cuando se envia y cuando se acepta o se
            // rechaza. No borra, por el criterio ya aplicado en los modulos
            // anteriores.
            'quotes.view',
            'quotes.create',
            'quotes.update',
            'quotes.change_status',
            'quotes.pdf',

            'reports.view',
        ],

        // El vendedor opera la cartera: registra y actualiza clientes y
        // contactos. No borra, porque el borrado es irreversible, ni cambia el
        // estado de un cliente ni decide quien es el contacto principal: son
        // decisiones que corresponden a la gerencia. Su perfil no depende de
        // permisos, asi que sigue funcionando sin ninguna de estas acciones.
        'Vendedor' => [
            'clients.view',
            'clients.create',
            'clients.update',

            'contacts.view',
            'contacts.create',
            'contacts.update',

            // A diferencia del estado de un cliente, mover la etapa de una
            // oportunidad es la labor diaria del vendedor y no una decision de
            // gerencia: sin esto podria capturar la operacion pero no
            // llevarla por las etapas. Tampoco borra, por el mismo criterio
            // que en los modulos anteriores: eliminar una actividad borra el
            // seguimiento registrado, y eso queda en la gerencia.
            'opportunities.view',
            'opportunities.create',
            'opportunities.update',
            'opportunities.change_status',

            'activities.view',
            'activities.create',
            'activities.update',

            // El vendedor trabaja el embudo de punta a punta: captura al
            // prospecto, lo califica y, cuando esta listo, lo convierte. Por eso
            // si puede cambiar el estado y convertir, igual que lleva las
            // oportunidades por sus etapas. No borra, por el criterio ya
            // aplicado en los modulos anteriores.
            'prospects.view',
            'prospects.create',
            'prospects.update',
            'prospects.change_status',
            'prospects.convert',

            // El vendedor es quien prepara y envia la propuesta comercial, que es
            // la labor diaria del ciclo de venta, y quien necesita el PDF para
            // mandárselo al cliente. No borra una cotizacion: el borrado es
            // irreversible y queda en la gerencia, igual que en los modulos
            // anteriores.
            'quotes.view',
            'quotes.create',
            'quotes.update',
            'quotes.change_status',
            'quotes.pdf',

            'reports.view',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Roles inicial de un usuario nuevo
    |--------------------------------------------------------------------------
    |
    | Se asigna al registrarse o al crear una cuenta desde el modulo de
    | usuarios, para que la persona entre al sistema con el perfil mas
    | restringido en lugar de quedarse sin permisos por descuido.
    |
    */

    'default_role' => 'Vendedor',

    /*
    |--------------------------------------------------------------------------
    | Rol con acceso completo
    |--------------------------------------------------------------------------
    |
    | Este rol conserva siempre todos los permisos del catálogo aunque la
    | interfaz le envíe una selección parcial: perder roles.update sobre el
    | rol que administra los roles dejaría la instalación sin forma de
    | recuperarlo.
    |
    | No es una regla de autorización, que siempre pasa por el Gate. Aquí
    | solo se evita una pérdida de permisos irreversible.
    |
    */

    'full_access_role' => 'Administrador',

];
