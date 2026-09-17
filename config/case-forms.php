<?php

// Implementación propia para Laravel. Nombres y organización contrastados con
// los formularios CP de Primero local; no se ejecutan ni importan sus seeds.
$field = static function (string $label, string $type = 'text', array $options = [], bool $supervisor = false): array {
    if ($type === 'select' && array_keys($options) === ['yes', 'no']) {
        $type = 'radio';
    }

    return compact('label', 'type', 'options', 'supervisor');
};
$yesNo = ['yes' => 'Sí', 'no' => 'No'];
$approval = ['pending' => 'Pendiente', 'approved' => 'Aprobado', 'rejected' => 'Rechazado'];
$risks = ['violence' => 'Violencia física', 'sexual_exploitation' => 'Explotación sexual', 'gbv' => 'Sobreviviente de violencia de género', 'street' => 'Persona en situación de calle', 'neglect' => 'Negligencia', 'separated' => 'Separación familiar', 'unaccompanied' => 'No acompañado/a', 'child_labour' => 'Trabajo infantil', 'trafficking' => 'Trata', 'disability' => 'Discapacidad', 'other' => 'Otro'];
$care = ['parents' => 'Con sus padres', 'relatives' => 'Con familiares', 'foster' => 'Familia de acogida', 'institution' => 'Cuidado residencial', 'independent' => 'Vida independiente', 'other' => 'Otro'];
$approvalFields = [
    'approved' => $field('Aprobado por el responsable', 'select', $yesNo, true),
    'approved_date' => $field('Fecha de aprobación', 'past_date', [], true),
    'approved_comments' => $field('Comentarios del responsable', 'textarea', [], true),
    'approval_status' => $field('Estado de aprobación', 'select', $approval, true),
];
$memberFields = [
    'name' => $field('Nombre'), 'role' => $field('Parentesco / rol en la familia'), 'agency_identifiers' => $field('Identificadores de agencias (separados por comas)'),
    'caregiver' => $field('¿Es cuidador de un niño de esta familia?', 'select', $yesNo),
    'role_notes' => $field('Notas sobre su rol', 'textarea'), 'other_names' => $field('Otros nombres'),
    'alive' => $field('¿Está vivo/a?', 'select', $yesNo), 'death_details' => $field('Si falleció, indique los detalles', 'textarea'),
    'age' => $field('Edad', 'number'), 'birth_date' => $field('Fecha de nacimiento', 'past_date'),
    'sex' => $field('Sexo', 'select', ['female' => 'Femenino', 'male' => 'Masculino', 'other' => 'Otro', 'not_specified' => 'No especificado']),
    'estimated_age' => $field('¿Edad estimada?', 'select', $yesNo), 'document' => $field('Documento de identidad'),
    'unhcr_id' => $field('Identificador ACNUR'), 'other_id' => $field('Otro identificador'),
    'language' => $field('Idioma'), 'religion' => $field('Religión'), 'ethnicity' => $field('Etnia'), 'sub_ethnicity_1' => $field('Subetnia 1'), 'sub_ethnicity_2' => $field('Subetnia 2'),
    'nationality' => $field('Nacionalidad'), 'occupation' => $field('Ocupación'),
    'address' => $field('Dirección actual', 'textarea'), 'permanent_address' => $field('¿Es una ubicación permanente?', 'select', $yesNo),
    'landmark' => $field('Punto de referencia'), 'location' => $field('Ubicación actual'),
    'last_address' => $field('Última dirección conocida', 'textarea'), 'last_location' => $field('Última ubicación conocida'),
    'phone' => $field('Teléfono / contacto'), 'notes' => $field('Notas adicionales', 'textarea'),
];

return [
    'navigation' => [
        'Información del registro' => ['approvals' => 'Aprobaciones', 'incidents' => 'Incidentes', 'referrals' => 'Derivación', 'linked' => 'Casos vinculados', 'assignments' => 'Transferencias / Asignaciones', 'changes' => 'Historial de cambios', 'access' => 'Registro de accesos'],
        'Identificación y registro' => ['incident_details' => 'Detalles del incidente', 'family_registration' => 'Registro de Familia', 'identity' => 'Identidad básica', 'protection' => 'Riesgos de protección', 'other_identity' => 'Otros datos de identidad', 'contact' => 'Ubicación y contacto'],
        'Confidencialidad de los datos' => ['consent' => 'Confidencialidad de los datos'],
        'Evaluación' => ['assessment' => 'Evaluación', 'risk_details' => 'Detalles de riesgos de protección'],
        'Detalles de la familia' => ['family' => 'Detalles de la familia'],
        'Plan del caso' => ['plan' => 'Plan del caso'],
        'Servicios y seguimiento' => ['care' => 'Acuerdos de cuidado', 'followup' => 'Seguimiento', 'services' => 'Servicios'],
        'Cierre' => ['closure' => 'Cierre'], 'Resumen' => ['summary' => 'Resumen'],
        'Fotos y audio' => ['media' => 'Fotos y audio'], 'Otros documentos' => ['documents' => 'Otros documentos'],
        'Derivaciones y transferencias' => ['transfers' => 'Derivaciones y transferencias'], 'Notas' => ['notes' => 'Notas'],
    ],
    'stages' => ['Caso nuevo', 'Plan del caso', 'Plan de cuidado', 'Plan de acción', 'Prestación de servicios', 'Servicio implementado', 'Caso cerrado'],
    'sections' => [
        'identity' => ['fields' => [
            'first_name' => $field('Primer nombre'), 'middle_name' => $field('Segundo nombre'), 'last_name' => $field('Apellidos'),
            'assessment_due' => $field('Fecha límite de evaluación', 'date'),
            'other_names' => $field('Otros nombres o formas de escribir el nombre'), 'nickname' => $field('Apodo'),
            'other_document_type' => $field('Tipo de otro documento de identidad'), 'other_document_number' => $field('Número de otro documento de identidad'),
            'unhcr_id' => $field('Número de registro individual ACNUR'), 'other_agency_id' => $field('Identificador de otra agencia'), 'other_agency_name' => $field('Nombre de otra agencia'),
            'marital_status' => $field('Estado civil', 'select', ['single' => 'Soltero/a', 'married' => 'Casado/a', 'union' => 'Unión de hecho', 'separated' => 'Separado/a', 'divorced' => 'Divorciado/a', 'widowed' => 'Viudo/a']),
            'occupation' => $field('Ocupación'), 'permanent_address' => $field('¿La dirección es permanente?', 'select', $yesNo),
        ]],
        'protection' => ['fields' => [
            'status' => $field('Estado de protección'), 'urgent' => $field('¿Riesgo de protección urgente?', 'select', $yesNo),
            'displacement' => $field('Situación de desplazamiento', 'select', ['resident' => 'Residente', 'displaced' => 'Desplazado/a', 'refugee' => 'Refugiado/a', 'returnee' => 'Retornado/a', 'other' => 'Otro']),
            'unhcr_code' => $field('Código de protección ACNUR'), 'concerns' => $field('Riesgos de protección', 'multi', $risks),
            'other' => $field('Si es otro, especifique'), 'needs_codes' => $field('Códigos de necesidades ACNUR'), 'disability' => $field('Tipo de discapacidad'),
            'special_needs' => $field('Necesidades especiales', 'textarea'), 'communication' => $field('Medio de comunicación más adecuado', 'textarea'),
        ]],
        'other_identity' => ['fields' => [
            'origin_country' => $field('País de origen'), 'last_address' => $field('Última dirección', 'textarea'), 'last_location' => $field('Última ubicación'),
            'last_phone' => $field('Último teléfono'), 'ethnicity' => $field('Etnia / clan / tribu'), 'sub_ethnicity_1' => $field('Subetnia 1'), 'sub_ethnicity_2' => $field('Subetnia 2'),
            'language' => $field('Idiomas'), 'religion' => $field('Religión'),
        ]],
        'consent' => ['fields' => [
            'source_other' => $field('Si es otro, especifique'), 'share_with' => $field('Se autoriza compartir información con', 'multi', ['family' => 'Familia', 'services' => 'Proveedores de servicios', 'authorities' => 'Autoridades', 'other' => 'Otros']),
            'share_with_other' => $field('Si se puede compartir con otros, especifique quién'), 'withheld_information' => $field('¿Qué información debe restringirse a una persona específica?', 'textarea'),
            'withheld_reason' => $field('Motivo de la restricción', 'multi', ['fear' => 'Temor de daño para sí mismo/a u otras personas', 'communicate' => 'Desea comunicar la información personalmente', 'other' => 'Otro motivo']), 'withheld_other' => $field('Otro motivo de restricción'),
            'retain_reason' => $field('Motivos para recopilar y conservar la información del caso', 'textarea'),
        ]],
        'assessment' => ['fields' => $approvalFields + [
            'started_on' => $field('Fecha de inicio de la evaluación', 'past_date'), 'plan_due' => $field('Fecha límite del plan del caso', 'date'),
        ]],
        'risk_details' => ['repeat' => ['label' => 'Detalles de riesgos de protección', 'fields' => [
            'concern' => $field('Tipo de riesgo de protección', 'select', $risks), 'period' => $field('Período en que se identificó', 'select', ['initial' => 'Registro inicial', 'assessment' => 'Evaluación', 'followup' => 'Seguimiento', 'other' => 'Otro']), 'details' => $field('Detalles del riesgo', 'textarea'),
        ]]],
        'incident_details' => ['repeat' => ['label' => 'Detalles del incidente', 'fields' => [
            'identifier' => $field('Identificación del incidente'), 'description' => $field('Incidente', 'textarea'), 'date' => $field('Fecha del incidente', 'past_date'),
            'area' => $field('Área del incidente'), 'area_other' => $field('Si es otra, especifique'), 'location' => $field('Ubicación del incidente'),
            'time_period' => $field('Momento del incidente'), 'time' => $field('Hora exacta', 'time'), 'violence_type' => $field('Tipo de violencia'),
            'previous_abuse' => $field('¿Ha sufrido violencia anteriormente?', 'select', $yesNo), 'previous_details' => $field('Si es así, describa brevemente', 'textarea'),
            'perpetrator_name' => $field('Nombre del presunto responsable'), 'perpetrator_nationality' => $field('Nacionalidad del presunto responsable'),
            'perpetrator_sex' => $field('Sexo del presunto responsable', 'select', ['female' => 'Femenino', 'male' => 'Masculino', 'unknown' => 'Desconocido']),
            'perpetrator_birth' => $field('Fecha de nacimiento del presunto responsable', 'past_date'), 'perpetrator_age' => $field('Edad del presunto responsable', 'number'),
            'perpetrator_document' => $field('Documento del presunto responsable'), 'perpetrator_other_type' => $field('Tipo de otro documento'), 'perpetrator_other_id' => $field('Número de otro documento'),
            'perpetrator_status' => $field('Estado civil del presunto responsable'), 'perpetrator_occupation' => $field('Ocupación del presunto responsable'), 'relationship' => $field('Relación con la persona afectada'),
        ]]],
        'plan' => ['fields' => ['approval_type' => $field('Tipo de aprobación', 'select', ['case_plan' => 'Plan del caso', 'action_plan' => 'Plan de acción'], true)] + $approvalFields + ['started_on' => $field('Fecha de inicio del plan del caso', 'past_date')],
            'repeat' => ['label' => 'Planes de intervención y servicios', 'fields' => [
                'service' => $field('Intervención / servicio a proporcionar'), 'provider' => $field('Persona / agencia responsable y contacto', 'textarea'),
                'goal' => $field('Objetivo de la intervención / servicio', 'textarea'), 'due_date' => $field('Fecha prevista de finalización', 'date'), 'success' => $field('¿Implementado satisfactoriamente?', 'select', $yesNo),
            ]]],
        'care' => ['fields' => ['caregiver' => $field('Nombre del cuidador actual'), 'arrangement' => $field('Acuerdo de cuidado actual', 'select', $care), 'started_on' => $field('¿Cuándo comenzó este acuerdo de cuidado?', 'past_date')],
            'repeat' => ['label' => 'Acuerdos de cuidado', 'fields' => [
                'same_caregiver' => $field('¿Es el mismo cuidador registrado anteriormente?', 'select', $yesNo), 'change_reason' => $field('Motivo del cambio de cuidador', 'textarea'),
                'include_referral' => $field('¿Incluir al cuidador actual en los detalles de derivación?', 'select', $yesNo), 'arrangement' => $field('Acuerdo de cuidado', 'select', $care),
                'other' => $field('Si es otro, especifique'), 'notes' => $field('Notas sobre el acuerdo', 'textarea'), 'agency' => $field('Agencia que proporciona el cuidado'),
                'caregiver' => $field('Nombre del cuidador'), 'other_names' => $field('Otros nombres del cuidador'), 'age' => $field('Edad del cuidador', 'number'),
                'birth_date' => $field('Fecha de nacimiento del cuidador', 'past_date'), 'relationship' => $field('Parentesco / relación'), 'started_on' => $field('Inicio del acuerdo', 'past_date'),
            ]]],
        'followup' => ['repeat' => ['label' => 'Seguimiento', 'fields' => [
            'type' => $field('Tipo de seguimiento'), 'service_type' => $field('Tipo de servicio'), 'assessment_type' => $field('Tipo de evaluación'),
            'concern' => $field('Tipo de riesgo de protección', 'select', $risks), 'due_date' => $field('Fecha límite del seguimiento', 'date'),
            'date' => $field('Fecha del seguimiento', 'past_date'), 'comments' => $field('Comentarios', 'textarea'),
        ]]],
        'services' => ['repeat' => ['label' => 'Servicios', 'fields' => [
            'response_type' => $field('Tipo de respuesta'), 'service_type' => $field('Tipo de servicio'), 'created_on' => $field('Fecha de creación', 'past_date'),
            'timeframe' => $field('Plazo de implementación', 'select', ['urgent' => 'Urgente', 'three_days' => 'Dentro de tres días', 'week' => 'Dentro de una semana', 'month' => 'Dentro de un mes', 'other' => 'Otro']),
            'appointment_date' => $field('Fecha de la cita', 'date'), 'agency' => $field('Agencia ejecutora'), 'provider' => $field('Proveedor del servicio'),
            'location' => $field('Lugar de prestación del servicio'), 'provider_name' => $field('Nombre de la persona que presta el servicio'), 'referred' => $field('¿Derivado?', 'select', $yesNo),
            'notes' => $field('Notas', 'textarea'), 'implemented' => $field('Servicio implementado', 'select', $yesNo), 'implemented_on' => $field('Fecha de implementación', 'past_date'), 'referral_notes' => $field('Notas del proveedor sobre la derivación', 'textarea'),
        ]]],
        'closure' => ['fields' => $approvalFields + [
            'reason' => $field('¿Cuál es el motivo para cerrar el expediente?', 'select', ['death' => 'Fallecimiento', 'formal' => 'Cierre formal', 'not_seen' => 'No localizado/a durante la verificación', 'repatriated' => 'Repatriación', 'transferred' => 'Transferencia', 'other' => 'Otro']),
            'other' => $field('Si es otro, especifique'), 'date' => $field('Fecha de cierre', 'past_date'),
        ]],
        'summary' => ['fields' => [
            'tracing_consent' => $field('Se ha obtenido consentimiento para divulgar información para localización', 'select', $yesNo),
            'wants_tracing' => $field('¿La persona quiere localizar a miembros de su familia?', 'select', $yesNo),
            'wants_reunification' => $field('¿La persona quiere la reunificación familiar?', 'select', $yesNo), 'reunification_details' => $field('Detalles de localización y reunificación', 'textarea'),
        ]],
        'transfers' => ['repeat' => ['label' => 'Transferencias y derivaciones documentadas', 'fields' => [
            'type' => $field('Tipo', 'select', ['referral' => 'Derivación', 'transfer' => 'Transferencia']),
            'local_user' => $field('Usuario local / responsable'), 'remote_user' => $field('Usuario destinatario'), 'agency' => $field('Agencia destinataria'),
            'status' => $field('Estado', 'select', ['pending' => 'Pendiente', 'accepted' => 'Aceptado', 'rejected' => 'Rechazado', 'completed' => 'Completado']),
            'rejected_reason' => $field('Motivo de rechazo', 'textarea'), 'notes' => $field('Notas', 'textarea'), 'by' => $field('Derivado o transferido por'),
            'service' => $field('Servicio'), 'remote' => $field('¿Es una transferencia a otro sistema?', 'select', $yesNo), 'consent' => $field('¿Cuenta con consentimiento para esta derivación?', 'select', $yesNo), 'date' => $field('Fecha de derivación o transferencia', 'past_date'),
        ]]],
        'notes' => ['repeat' => ['label' => 'Notas', 'fields' => ['date' => $field('Fecha', 'past_date'), 'subject' => $field('Asunto'), 'notes' => $field('Notas', 'textarea'), 'manager' => $field('Responsable')]]],
        'marks' => ['repeat' => ['label' => 'Marcas', 'fields' => ['date' => $field('Fecha de revisión', 'date'), 'reason' => $field('Motivo de la marca', 'textarea')]]],
    ],
    'family_fields' => [
        'number' => $field('Número de familia'), 'nationality' => $field('Nacionalidad'), 'ethnicity' => $field('Etnia / clan / tribu'),
        'language' => $field('Idiomas'), 'address' => $field('Dirección de la familia', 'textarea'), 'landmark' => $field('Punto de referencia'),
        'location' => $field('Ubicación de la familia'), 'phone' => $field('Teléfono de la familia'), 'contact_notes' => $field('Notas sobre ubicación y contacto', 'textarea'),
        'notes' => $field('Notas sobre la familia', 'textarea'), 'additional_notes' => $field('Notas adicionales', 'textarea'),
    ],
    'member_fields' => $memberFields,
];
