<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vistas del calendario
    |--------------------------------------------------------------------------
    |
    | El calendario no tiene datos propios: dibuja las actividades que ya
    | existen. Aquí solo se configura cómo se corta el tiempo en pantalla. La
    | clave viaja en la URL y en el atributo "view" de cada evento, y la
    | etiqueta viaja al frontend con ella, como en config/activities.php, para
    | que el nombre de cada vista esté escrito una sola vez.
    |
    */

    'views' => [
        'month' => 'Mes',
        'week' => 'Semana',
        'day' => 'Día',
    ],

    'default_view' => 'month',

    /*
    |--------------------------------------------------------------------------
    | Primer dia de la semana
    |--------------------------------------------------------------------------
    |
    | Número de Carbon: 1 es lunes y 0 es domingo. Se declara aquí y no se
    | deja en el valor por defecto de Carbon, que depende de la configuración
    | regional de PHP: una semana que empieza en domingo un día y en lunes
    | otro haría que el mismo enlace mostrara rejillas distintas.
    |
    */

    'week_starts_at' => 1,

    /*
    |--------------------------------------------------------------------------
    | Longitud del titulo de un evento
    |--------------------------------------------------------------------------
    |
    | La descripción admite hasta 5000 caracteres (activities.php), pero en
    | una celda del calendario solo caben unas cuantas palabras. El backend
    | recorta el título una sola vez al construir el evento para que las tres
    | vistas no muestren longitudes distintas.
    |
    */

    'title_max' => 120,

];
