<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Reportes
    |--------------------------------------------------------------------------
    |
    | Los reportes no son un catalogo propio: los nombres de las etapas, los
    | estados y las etapas de cierre viven en config/opportunities.php,
    | config/quotes.php y config/prospects.php, y llegan al componente ya
    | traducidos, igual que en el resto de los modulos.
    |
    | Lo que queda aqui es lo que el backend tiene que decir y el texto generico
    | no dice bien: el nombre de la fecha que se recibio con un formato que no es
    | el del filtro, y el rango invertido. Un enlace mal escrito es un error de
    | quien lo escribio, y el mensaje tiene que sealo en espanol.
    |
    */

    'invalid_date' => 'Las fechas del filtro deben tener el formato AAAA-MM-DD.',

    'invalid_range' => 'La fecha inicial no puede ser posterior a la fecha final.',

    /*
    | Los importes de los reportes no se reconstruyen en el navegador: llegan como
    | cadena de dos decimales, igual que en el dashboard, y el formateador de
    | resources/js/lib/format.js los presenta como pesos.
    |
    | "unassigned" es la unica etiqueta que el backend tiene que inventar, y solo
    | aparece en el reporte por responsable. Las columnas de responsable son
    | nullables para no perder el historial comercial cuando se elimina una
    | cuenta, de modo que un periodo puede incluir operaciones que hoy no tienen
    | a quien atribuirlas. Decirlo asi es mas honesto que dejar la fila sin nombre o
    | esconderla.
    |
    */

    'unassigned' => 'Sin responsable',

];
