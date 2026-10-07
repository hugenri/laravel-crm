<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tipos de actividad
    |--------------------------------------------------------------------------
    |
    | La clave es el valor de la columna "type" y la etiqueta lo que muestra
    | la interfaz. Agregar un tipo es una entrada más aquí, sin tocar
    | controladores ni Vue.
    |
    */

    'types' => [
        'llamada' => 'Llamada',
        'correo' => 'Correo',
        'reunion' => 'Reunión',
        'nota' => 'Nota',
        'tarea' => 'Tarea',
    ],

    /*
    |--------------------------------------------------------------------------
    | Estados de actividad
    |--------------------------------------------------------------------------
    |
    | El alcance pide tres estados y no un sistema de transiciones. Son un
    | dato del registro, igual que el tipo o la fecha: de ellos no se deriva
    | nada.
    |
    */

    'statuses' => [
        'pendiente' => 'Pendiente',
        'completada' => 'Completada',
        'cancelada' => 'Cancelada',
    ],

    'initial_status' => 'pendiente',

    /*
    |--------------------------------------------------------------------------
    | Columnas ordenables del listado
    |--------------------------------------------------------------------------
    |
    | Lista blanca, como en el listado de oportunidades, para que la columna
    | que llega del request nunca se interpole directamente en la consulta.
    |
    */

    'sortable' => [
        'activity_date',
        'type',
        'status',
        'created_at',
    ],

    'default_sort' => 'activity_date',

    'sort_directions' => ['asc', 'desc'],

    /*
    |----------------------------------------------------------------------
    | Limites de los campos
    |----------------------------------------------------------------------
    */

    'description_max' => 5000,

];
