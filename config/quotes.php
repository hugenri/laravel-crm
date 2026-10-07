<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Estados de la cotizacion
    |--------------------------------------------------------------------------
    |
    | La clave es el valor de la columna "status" y la etiqueta lo que muestra
    | la interfaz. Agregar un estado es una entrada más aquí, sin tocar
    | controladores, policies ni Vue.
    |
    */

    'statuses' => [
        'borrador' => 'Borrador',
        'enviada' => 'Enviada',
        'aceptada' => 'Aceptada',
        'rechazada' => 'Rechazada',
        'vencida' => 'Vencida',
    ],

    /*
    |--------------------------------------------------------------------------
    | Estado con el que nace una cotizacion
    |--------------------------------------------------------------------------
    |
    | El alta siempre nace en borrador. Enviar una cotización es la acción de
    | cambio de estado, que exige su propio permiso, así que nunca es un
    | efecto secundario de darla de alta.
    |
    */

    'initial_status' => 'borrador',

    /*
    |--------------------------------------------------------------------------
    | Estados que cuentan como cotizaciones pendientes
    |--------------------------------------------------------------------------
    |
    | Lo que el dashboard muestra como "cotizaciones pendientes": las que el
    | equipo aún tiene que trabajar. Una aceptada, rechazada o vencida ya
    | recibió respuesta y salió de la cuenta.
    |
    */

    'pending_statuses' => ['borrador', 'enviada'],

    /*
    |----------------------------------------------------------------------
    | Estado de la propuesta que el cliente acepto
    |----------------------------------------------------------------------
    |
    | El único estado del catálogo del que el reporte de cotizaciones saca
    | una cifra: cuánto se aceptó. Vive aquí y no escrito dentro del reporte
    | porque es el mismo dato que usan el listado y el PDF, y en tres sitios
    | distintos una constante acaba dejando de coincidir con el catálogo.
    |
    */

    'accepted_status' => 'aceptada',

    /*
    |--------------------------------------------------------------------------
    | Estados en los que todavia se puede editar el contenido
    |--------------------------------------------------------------------------
    |
    | Una cotización en borrador es un documento en construcción y se corrige
    | sin restricción. En cuanto pasa a enviada, el cliente ya la tiene a la
    | vista: cambiarle importes o partidas sin que nadie lo note convierte el
    | documento en algo distinto de lo que el cliente recibió. Por eso
    | quotes.update solo alcanza mientras está en borrador; el resto se
    | corrige con una cotización nueva.
    |
    */

    'editable_statuses' => ['borrador'],

    /*
    |--------------------------------------------------------------------------
    | Estados a los que se puede llegar desde cada uno
    |--------------------------------------------------------------------------
    |
    | El ciclo comercial es lineal: se envía, el cliente contesta y la
    | propuesta queda aceptada o rechazada. Las transiciones que no aparecen
    | aquí no se pueden pedir por la vía de cambio de estado.
    |
    | "vencida" vuelve a "borrador" porque una propuesta sin respuesta a
    | tiempo puede corregirse y reenviarse. "aceptada" y "rechazada" son
    | finales: el cliente ya respondió, y una decisión distinta se registra
    | con una cotización nueva.
    |
    */

    'transitions' => [
        'borrador' => ['enviada', 'vencida'],
        'enviada' => ['aceptada', 'rechazada', 'vencida'],
        'vencida' => ['borrador'],
        'aceptada' => [],
        'rechazada' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tasa de IVA por omision
    |--------------------------------------------------------------------------
    |
    | Porcentaje, no fracción: 16 significa dieciséis por ciento. El
    | formulario la ofrece precargada y el usuario puede ajustarla por
    | cotización, pero el impuesto y el total los calcula siempre el servidor
    | a partir de la tasa guardada, nunca el navegador.
    |
    */

    'tax_rate' => 16,

    /*
    |--------------------------------------------------------------------------
    | Prefijo del folio
    |--------------------------------------------------------------------------
    |
    | El folio se completa con el identificador de la cotización, que es
    | único y nunca se reutiliza, así que dos altas simultáneas no pueden
    | recibir el mismo folio. No hay contador que mantener ni que se
    | desincrone.
    |
    */

    'folio_prefix' => 'COT-',

    'folio_padding' => 6,

    /*
    |--------------------------------------------------------------------------
    | Vigencia por omision
    |--------------------------------------------------------------------------
    |
    | Días que se ofrecen como "fecha de vigencia" en el alta, contados desde
    | la emisión. El backend acepta cualquier otra fecha, siempre igual o
    | posterior a la de emisión.
    |
    */

    'validity_days' => 15,

    /*
    |--------------------------------------------------------------------------
    | Columnas ordenables del listado
    |--------------------------------------------------------------------------
    */

    'sortable' => [
        'folio',
        'status',
        'issue_date',
        'valid_until',
        'subtotal',
        'total',
        'created_at',
    ],

    'default_sort' => 'created_at',

    'sort_directions' => ['asc', 'desc'],

    /*
    |--------------------------------------------------------------------------
    | Limites de los campos
    |--------------------------------------------------------------------------
    |
    | "totals_max" es el tope de decimal(15, 2), el máximo que admiten las
    | columnas subtotal, tax_amount y total. Vive aquí y no en la migración
    | para que el mensaje de error pueda explicarlo. El resto de topes son
    | los del formulario, más estrictos que la columna, para que el error
    | llegue antes y sea entendible.
    |
    | "totals_max" y "unit_price_max" son cadenas y no números: con catorce
    | dígitos significativos un float no representa 9999999999999.99 y al
    | convertirlo a texto daría 10000000000000, un tope diez veces mayor que
    | el de la columna.
    |
    */

    'totals_max' => '9999999999999.99',

    'unit_price_max' => '99999999.99',

    'quantity_max' => '999999.999',

    'tax_rate_max' => 100,

    'items_min' => 1,

    'items_max' => 50,

    'description_max' => 255,

    'notes_max' => 5000,

];
