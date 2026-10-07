<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Correos de administrador
    |--------------------------------------------------------------------------
    |
    | Estas cuentas se saltan toda verificación de autorización mientras la
    | aplicación no tiene sistema de roles, y son las únicas que llegan al
    | módulo de administración de usuarios. Cuando entren los roles y
    | permisos, esta lista se sustituye por una comprobación de rol en
    | App\Policies\UserPolicy y en el hook Gate::before de
    | App\Providers\AppServiceProvider.
    |
    */

    'admin_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CRM_ADMIN_EMAILS', 'admin@crm.test'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Autorregistro
    |--------------------------------------------------------------------------
    |
    | Los despliegues corporativos suelen crear las cuentas desde el módulo
    | de administración en lugar de aceptar altas públicas. Desactivarlo
    | oculta las rutas de registro y el enlace de la pantalla de login sin
    | tocar una sola línea de código.
    |
    */

    'registration_enabled' => filter_var(
        env('CRM_REGISTRATION_ENABLED', true),
        FILTER_VALIDATE_BOOLEAN,
    ),

    /*
    |--------------------------------------------------------------------------
    | Paginación
    |--------------------------------------------------------------------------
    |
    | Filas por página de los listados administrativos. Vive aquí para que
    | los módulos de CRM compartan este valor en lugar de repetirlo en cada
    | controlador.
    |
    */

    'per_page' => (int) env('CRM_PER_PAGE', 15),

];
