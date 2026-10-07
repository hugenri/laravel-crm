<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Estados del prospecto
    |--------------------------------------------------------------------------
    |
    | La clave es el valor de la columna "status" y la etiqueta lo que muestra
    | la interfaz; agregar un estado es una entrada más aquí, sin tocar
    | controladores ni Vue.
    |
    | "convertido" es un estado terminal y no se ofrece en los formularios: lo
    | escribe App\Actions\Prospects\ConvertProspect, en el mismo movimiento
    | que crea el cliente y el contacto. Ver "qualified_statuses" abajo.
    |
    */

    'statuses' => [
        'nuevo' => 'Nuevo',
        'contactado' => 'Contactado',
        'calificado' => 'Calificado',
        'no_interesado' => 'No interesado',
        'convertido' => 'Convertido',
    ],

    /*
    |--------------------------------------------------------------------------
    | Estados que se pueden elegir a mano
    |--------------------------------------------------------------------------
    |
    | Los únicos que ofrecen el formulario de alta y la acción de cambio de
    | estado. "convertido" queda fuera a propósito: llegar a él crea un cliente
    | y un contacto, así que no puede ser un efecto secundario de editar un
    | campo de texto. Tener prospects.update no basta para convertir, igual
    | que en clientes.
    |
    */

    'qualified_statuses' => ['nuevo', 'contactado', 'calificado', 'no_interesado'],

    /*
    |--------------------------------------------------------------------------
    | Valores con los que nace un prospecto
    |--------------------------------------------------------------------------
    |
    | Se declaran de forma explícita para que App\Models\Prospect no tenga que
    | suponer que el primer elemento del catálogo es el inicial.
    |
    */

    'initial_status' => 'nuevo',

    /*
    | Estado que la conversion impone. No se escribe de ninguna otra forma.
    */

    'converted_status' => 'convertido',

    /*
    |--------------------------------------------------------------------------
    | Fuentes del prospecto
    |--------------------------------------------------------------------------
    |
    | Como los tipos de cliente: la clave es lo que se guarda y la etiqueta lo
    | que se muestra. El filtro por fuente del listado se arma con este mismo
    | mapa, así que el catálogo no se duplica en la vista.
    |
    */

    'sources' => [
        'referido' => 'Referido',
        'sitio_web' => 'Sitio web',
        'llamada' => 'Llamada',
        'evento' => 'Evento',
        'redes_sociales' => 'Redes sociales',
        'otro' => 'Otro',
    ],

    /*
    |--------------------------------------------------------------------------
    | Columnas ordenables del listado
    |--------------------------------------------------------------------------
    */

    'sortable' => [
        'name',
        'company',
        'source',
        'status',
        'follow_up_date',
        'created_at',
    ],

    'default_sort' => 'created_at',

    'sort_directions' => ['asc', 'desc'],

    /*
    |--------------------------------------------------------------------------
    | Limites de los campos
    |--------------------------------------------------------------------------
    |
    | Viven aquí y no en la migración, como en los demás módulos, para que el
    | mensaje de error en español pueda explicar el motivo.
    |
    */

    'name_max' => 150,

    'company_max' => 150,

    'phone_max' => 40,

    'notes_max' => 5000,

];
