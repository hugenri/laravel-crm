<?php

return [

    'name' => 'Nombre',
    'company' => 'Empresa',
    'phone' => 'Teléfono',
    'email' => 'Correo electrónico',
    'source' => 'Fuente',
    'status' => 'Estado',
    'user' => 'Responsable',
    'follow_up_date' => 'Fecha de seguimiento',
    'notes' => 'Notas',

    'no_prospects' => 'No hay prospectos que coincidan con los criterios indicados.',
    'no_user' => 'Sin responsable asignado',
    'no_company' => 'Sin empresa registrada',
    'no_follow_up' => 'Sin seguimiento programado',
    'user_inactive' => 'El responsable seleccionado está inactivo.',
    'invalid_status' => 'El estado indicado no es válido.',
    'invalid_source' => 'La fuente indicada no es válida.',
    'status_not_selectable' => 'El estado indicado no es válido.',
    'status_not_available' => 'Ese estado no se puede elegir a mano: Convertido solo lo establece la conversión.',
    'status_not_editable' => 'El estado se cambia desde una acción propia, no al editar el prospecto.',
    'status_updated' => 'El estado del prospecto se actualizó.',
    'conversion_fields' => 'El cliente y el contacto los asigna la conversión, no el formulario del prospecto.',
    'already_converted' => 'Este prospecto ya fue convertido. La conversión no se puede repetir.',

    'created' => 'El prospecto se registró correctamente.',
    'updated' => 'El prospecto se actualizó correctamente.',
    'deleted' => 'El prospecto se eliminó correctamente.',
    'converted' => 'El prospecto se convirtió en cliente correctamente.',

    'title' => 'Prospectos',
    'new_title' => 'Nuevo prospecto',
    'edit_title' => 'Editar prospecto',
    'register' => 'Registrar prospecto',
    'save' => 'Guardar cambios',

    'search_placeholder' => 'Buscar por nombre, empresa, teléfono o correo',
    'filter_status' => 'Filtrar por estado',
    'filter_source' => 'Filtrar por fuente',
    'filter_user' => 'Filtrar por responsable',
    'all_statuses' => 'Todos los estados',
    'all_sources' => 'Todas las fuentes',
    'all_users' => 'Todos los responsables',

    'basic_information' => 'Datos del prospecto',
    'follow_up_section' => 'Seguimiento',
    'conversion_section' => 'Conversión a cliente',

    'mark_contacted' => 'Marcar contactado',
    'mark_qualified' => 'Marcar calificado',
    'mark_not_interested' => 'Marcar no interesado',
    'move_status' => 'Cambiar estado',
    'status_hint' => 'El estado Convertido se alcanza con la conversión, porque crea el cliente y su contacto.',

    'convert' => 'Convertir a cliente',
    'convert_title' => 'Convertir prospecto',
    'convert_hint' => 'Se crearán un cliente y un contacto principal con los datos que confirmes aquí. El prospecto no se borra: queda registrado como convertido.',
    'convert_client_section' => 'Cliente a registrar',
    'convert_contact_section' => 'Contacto principal',
    'client_type' => 'Tipo de cliente',
    'client_notes' => 'Notas del cliente',
    'rfc' => 'RFC',
    'address' => 'Dirección',

    'convert_showing' => 'Generado al convertir este prospecto',
    'convert_showing_none' => 'Este prospecto todavía no se ha convertido.',

    'confirm_delete' => '¿Seguro que desea eliminar este prospecto? Esta acción no se puede deshacer.',
    'converted_not_deletable' => 'Este prospecto ya se convirtió, así que no se puede eliminar: es el registro de quién entró a la cartera y desde qué fuente.',

];
