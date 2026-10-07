<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Calendario
    |--------------------------------------------------------------------------
    |
    | El calendario dibuja actividades, asi que sus textos no son un catálogo
    | propio: los nombres de las vistas viven en config/calendar.php, igual que
    | los tipos y los estados viven en config/activities.php, y llegan al
    | componente ya traducidos.
    |
    | Lo que queda aqui son los mensajes que produce el backend al recibir un
    | enlace mal escrito, porque la validación de la URL no se puede dejar en el
    | texto generico que Laravel arma para la regla: "el campo view es
    | inválido" no dice qué es la vista ni cuál era el valor esperado.
    |
    */

    'invalid_view' => 'La vista de calendario debe ser una de: :values.',
    'invalid_anchor' => 'La fecha de referencia debe tener el formato AAAA-MM-DD.',

];
