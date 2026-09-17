<?php

return [
    'permissions' => ['ver casos', 'crear casos', 'editar casos', 'asignar casos', 'supervisar casos', 'ver historial de casos', 'gestionar casos vbg'],
    'types' => ['general' => 'Protección general', 'child_protection' => 'Protección de la niñez', 'vbg' => 'Violencia basada en género (VBG)'],
    'risks' => ['pending' => 'Por evaluar', 'low' => 'Bajo', 'medium' => 'Medio', 'high' => 'Alto'],
    'sexes' => ['female' => 'Femenino', 'male' => 'Masculino', 'other' => 'Otro', 'not_specified' => 'No especificado'],
    'consents' => ['pending' => 'Pendiente', 'granted' => 'Otorgado', 'declined' => 'No otorgado'],
    'sections' => [
        'identity' => ['title' => 'Identificación y registro', 'icon' => 'ri-user-line'],
        'contact' => ['title' => 'Ubicación y contacto', 'icon' => 'ri-map-pin-line'],
        'family' => ['title' => 'Familia y persona de apoyo', 'icon' => 'ri-team-line'],
        'consent' => ['title' => 'Consentimiento y confidencialidad', 'icon' => 'ri-shield-check-line'],
        'assessment' => ['title' => 'Evaluación inicial', 'icon' => 'ri-file-list-3-line'],
    ],
    'fields' => [
        'form_data' => 'Formularios del expediente', 'family_record_id' => 'Grupo familiar', 'status' => 'Estado del caso',
        'full_name' => 'Nombre completo', 'document_type' => 'Tipo de documento', 'document_number' => 'Número de documento',
        'birth_date' => 'Fecha de nacimiento', 'age_at_registration' => 'Edad estimada al registrar', 'sex' => 'Sexo',
        'nationality' => 'Nacionalidad', 'registered_on' => 'Fecha de registro', 'case_type' => 'Tipo de caso',
        'proyecto_id' => 'Proyecto', 'assigned_to' => 'Responsable', 'state_id' => 'Estado', 'municipality_id' => 'Municipio',
        'parish_id' => 'Parroquia', 'address' => 'Dirección', 'phone' => 'Teléfono', 'safe_contact' => 'Forma segura de contacto',
        'family_notes' => 'Información familiar', 'support_person' => 'Persona de apoyo o cuidador', 'support_relationship' => 'Parentesco o relación',
        'support_phone' => 'Teléfono de la persona de apoyo', 'consent_status' => 'Consentimiento para la gestión del caso',
        'consent_source' => 'Consentimiento obtenido de', 'consent_date' => 'Fecha del consentimiento',
        'consent_notes' => 'Detalles del consentimiento', 'share_services' => 'Autorización para compartir con servicios',
        'share_reports' => 'Autorización para reportes no identificables', 'risk_level' => 'Nivel de riesgo',
        'presenting_needs' => 'Necesidades identificadas', 'immediate_actions' => 'Acciones inmediatas',
    ],
];
