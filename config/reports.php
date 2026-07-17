<?php

return [
    'organizations' => [
        'UNICEF - Implementación Directa', 'ASEINC', 'Asociación Civil Tinta Violeta', 'ASONACOP',
        'CARITAS Carúpano', 'Caritas VEN', 'CECODAP', 'CEPIN', 'CISP', 'F. Finampyme',
        'Fe y Alegría', 'Fundación Luz y Vida', 'Fundación Rehabilitarte', 'FUNDAINIL',
        'FUNDANA', 'IRFA', 'MPPS', 'PALUZ', 'Proyecto Esperanza', 'RCDB', 'WV Venezuela', 'INN',
        'Otro Socio Implementador',
    ],

    'installation_types' => [
        'Centro de Salud', 'Campamentos transitorios - gestionados ONU',
        'Campamentos transitorios - del gobierno', 'Asentamientos informales',
        'Comunidad / Espacio Comunitario', 'Escuela / Espacio Educativo',
        'Espacio Amigable para la Infancia',
    ],

    'sectors' => [
        ['name' => 'Agua, Saneamiento e Higiene (WASH)', 'slug' => 'wash'],
        ['name' => 'Protección de la niñez', 'slug' => 'proteccion-ninez'],
        ['name' => 'Nutrición', 'slug' => 'nutricion'],
        ['name' => 'Educación', 'slug' => 'educacion'],
        ['name' => 'Salud', 'slug' => 'salud'],
        ['name' => 'Rendición de Cuentas (AAP)', 'slug' => 'aap'],
        ['name' => 'Prevención de Explotación y Abuso Sexual (PEAS)', 'slug' => 'peas'],
        ['name' => 'Cambio Social y de Comportamiento (SBC)', 'slug' => 'sbc'],
        ['name' => 'Logística, Dotaciones y Diagnósticos', 'slug' => 'logistica'],
    ],

    'breakdown_schemes' => [
        'tradicional' => [
            'label' => 'Edades tradicionales',
            'fields' => [
                'girls_0_5' => 'Niñas (0 a 5 años)', 'boys_0_5' => 'Niños (0 a 5 años)',
                'girls_6_11' => 'Niñas (6 a 11 años)', 'boys_6_11' => 'Niños (6 a 11 años)',
                'girls_12_17' => 'Niñas (12 a 17 años)', 'boys_12_17' => 'Niños (12 a 17 años)',
                'women_18_59' => 'Mujeres (18 a 59 años)', 'men_18_59' => 'Hombres (18 a 59 años)',
                'women_60_plus' => 'Mujeres (60 años o más)', 'men_60_plus' => 'Hombres (60 años o más)',
            ],
        ],
        'educacion_nutricion' => [
            'label' => 'Educación y nutrición',
            'fields' => [
                'girls_0_2' => 'Niñas de 0 a 2 años', 'boys_0_2' => 'Niños de 0 a 2 años',
                'girls_3_5' => 'Niñas de 3 a 5 años', 'boys_3_5' => 'Niños de 3 a 5 años',
                'girls_6_11' => 'Niñas de 6 a 11 años', 'boys_6_11' => 'Niños de 6 a 11 años',
                'girls_12_17' => 'Niñas de 12 a 17 años', 'boys_12_17' => 'Niños de 12 a 17 años',
                'women_18_59' => 'Mujeres de 18 a 59 años', 'men_18_59' => 'Hombres de 18 a 59 años',
                'women_60_plus' => 'Mujeres de 60 años o más', 'men_60_plus' => 'Hombres de 60 años o más',
            ],
        ],
        'salud' => [
            'label' => 'Desagregación de salud',
            'fields' => [
                'girls_0_5' => 'Niñas de 0 a 5 años', 'boys_0_5' => 'Niños de 0 a 5 años',
                'girls_6_19' => 'Niñas de 6 a 19 años', 'boys_6_19' => 'Niños de 6 a 19 años',
                'women_20_49' => 'Mujeres de 20 a 49 años', 'men_20_49' => 'Hombres de 20 a 49 años',
                'women_50_59' => 'Mujeres de 50 a 59 años', 'men_50_59' => 'Hombres de 50 a 59 años',
                'women_60_69' => 'Mujeres de 60 a 69 años', 'men_60_69' => 'Hombres de 60 a 69 años',
                'women_70_79' => 'Mujeres de 70 a 79 años', 'men_70_79' => 'Hombres de 70 a 79 años',
                'women_80_plus' => 'Mujeres de 80 años o más', 'men_80_plus' => 'Hombres de 80 años o más',
            ],
        ],
        'inclusiva' => [
            'label' => 'Desagregación inclusiva',
            'fields' => [
                'girls_0_17' => 'Niñas de 0 a 17 años', 'boys_0_17' => 'Niños de 0 a 17 años',
                'women_18_59' => 'Mujeres de 18 a 59 años', 'men_18_59' => 'Hombres de 18 a 59 años',
                'women_60_plus' => 'Mujeres de 60 años o más', 'men_60_plus' => 'Hombres de 60 años o más',
                'nonbinary_0_17' => 'No binario de 0 a 17 años',
                'nonbinary_18_59' => 'No binario de 18 a 59 años',
                'nonbinary_60_plus' => 'No binario de 60 años o más',
                'not_reported' => 'No contestó/no se identificó',
            ],
        ],
    ],
];
