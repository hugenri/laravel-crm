<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tipos de cliente
    |--------------------------------------------------------------------------
    |
    | La clave es el valor de la columna "type" y la etiqueta lo que muestra
    | la interfaz. La clave se usa en validaciones y en la regla del RFC, así
    | que agregar un tipo es una entrada más aquí, sin tocar controladores ni
    | Vue.
    |
    */

    'types' => [
        'empresa' => 'Empresa',
        'persona' => 'Persona',
    ],

    /*
    |--------------------------------------------------------------------------
    | Estados del cliente
    |--------------------------------------------------------------------------
    |
    | Un cliente inactivo conserva su información y sus contactos: la
    | inactivación reemplaza a la baja para que los módulos futuros
    | (oportunidades, cotizaciones) puedan seguir consultando su historial.
    |
    */

    'statuses' => [
        'activo' => 'Activo',
        'inactivo' => 'Inactivo',
    ],

    /*
    |--------------------------------------------------------------------------
    | Estado que representa un cliente en operación
    |--------------------------------------------------------------------------
    |
    | La clave del catálogo de arriba que significa "operativo". Se declara
    | de forma explícita para que App\Models\Client no tenga que suponer que
    | el primer elemento del arreglo es el activo.
    |
    */

    'active_status' => 'activo',

    /*
    |--------------------------------------------------------------------------
    | Columnas ordenables del listado
    |--------------------------------------------------------------------------
    */

    'sortable' => ['name', 'rfc', 'email', 'status', 'type', 'created_at'],

    'default_sort' => 'name',

    'sort_directions' => ['asc', 'desc'],

];
