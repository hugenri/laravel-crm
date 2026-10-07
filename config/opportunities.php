<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Etapas de la oportunidad
    |--------------------------------------------------------------------------
    |
    | La clave es el valor de la columna "stage" y la etiqueta lo que muestra
    | la interfaz; agregar una etapa es una entrada más aquí, sin tocar
    | controladores ni Vue.
    |
    | "ganada" y "perdida" son etapas de cierre y por eso aparecen también en
    | "closing_stages": el formulario ordinario no las ofrece, cerrar y
    | reabrir son acciones propias, con su propio permiso.
    |
    */

    'stages' => [
        'nueva' => 'Nueva',
        'contactado' => 'Contactado',
        'calificacion' => 'Calificación',
        'propuesta' => 'Propuesta',
        'negociacion' => 'Negociación',
        'ganada' => 'Ganada',
        'perdida' => 'Perdida',
    ],

    /*
    | Etapas que representan una oportunidad todavia en curso
    |--------------------------------------------------------------------------
    |
    | Son las únicas que ofrece el formulario de creación y edición. Una etapa
    | de cierre solo se alcanza con la acción dedicada: tener
    | opportunities.update no basta para cerrar una operación.
    |
    */

    'open_stages' => ['nueva', 'contactado', 'calificacion', 'propuesta', 'negociacion'],

    /*
    | Etapas de cierre y el estado que cada una impone
    |--------------------------------------------------------------------------
    |
    | El estado nunca se escribe de forma independiente: se deriva de la
    | etapa, para que las dos nunca queden incoherentes. Cuando la etapa pasa
    | a ser de cierre, el estado toma el valor de aquí. Al reabrir, el estado
    | vuelve a "abierta" y la etapa se conserva.
    |
    */

    'closing_stages' => [
        'ganada' => 'ganada',
        'perdida' => 'perdida',
    ],

    /*
    | Estados de la oportunidad
    |--------------------------------------------------------------------------
    |
    | La etapa dice por dónde va la operación; el estado dice si sigue abierta
    | o ya terminó. Son conceptos distintos y conviven: una oportunidad en
    | etapa "Negociación" está en estado "Abierta".
    |
    */

    'statuses' => [
        'abierta' => 'Abierta',
        'ganada' => 'Ganada',
        'perdida' => 'Perdida',
    ],

    'open_status' => 'abierta',

    /*
    | Valores con los que nace una oportunidad
    |--------------------------------------------------------------------------
    */

    'initial_stage' => 'nueva',

    'initial_status' => 'abierta',

    /*
    | Columnas ordenables del listado
    |--------------------------------------------------------------------------
    */

    'sortable' => [
        'name',
        'amount',
        'stage',
        'status',
        'expected_close_date',
        'created_at',
    ],

    'default_sort' => 'created_at',

    'sort_directions' => ['asc', 'desc'],

    /*
    |----------------------------------------------------------------------
    | Limites de los campos
    |----------------------------------------------------------------------
    |
    | "amount_max" es el tope del monto estimado. decimal(15, 2) admite
    | quince dígitos enteros, así que el límite vive aquí y no en la
    | migración para que el mensaje de error en español pueda explicarlo.
    |
    */

    'amount_max' => 99999999999.99,

    'notes_max' => 5000,

    'name_max' => 150,

];
