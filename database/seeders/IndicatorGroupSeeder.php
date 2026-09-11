<?php

namespace Database\Seeders;

use App\Models\Indicador;
use App\Models\IndicatorGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IndicatorGroupSeeder extends Seeder
{
    /**
     * Grupos e indicadores del marco lógico de Protección de NNA.
     * Los códigos se guardan sin el prefijo GCLPR para admitir ambos formatos.
     *
     * @var array<int, array{name: string, description: string, indicators: array<string, string>}>
     */
    private const GROUPS = [
        1 => [
            'name' => 'Apoyo psicosocial (SMAPS)',
            'description' => 'NNA, adolescentes y cuidadores con apoyo psicosocial comunitario.',
            'indicators' => [
                'SCA12/IC1/IE1' => 'NNA en actividades grupales',
                'SCA12/IC2/IE1' => 'NNA en actividades individuales',
                'SCA12/IC1/IE2' => 'Cuidadores en actividades grupales',
                'SCA12/IC2/IE2' => 'Cuidadores en actividades individuales',
            ],
        ],
        2 => [
            'name' => 'Gestión de casos y asistencia legal',
            'description' => 'NNA que recibieron gestión de casos individual, asesoramiento o asistencia legal.',
            'indicators' => [
                'SCA10/IC1/IE2' => 'Nuevos casos de gestión de protección',
                'SCA17/IC1/IE1' => 'Asesoramiento y orientación legal',
                'SCA18/IC1/IE1' => 'Asistencia legal de protección',
            ],
        ],
        3 => [
            'name' => 'Prevención y respuesta a la VBG',
            'description' => 'Mujeres, niñas y niños con acciones de mitigación, prevención o respuesta a la VBG.',
            'indicators' => [
                'SCA10/IC1/IE1' => 'Gestión de casos de VBG',
                'SCA17/IC1/IE2' => 'Orientación legal',
                'SCA12/IC1/IE3' => 'SMAPS grupal',
                'SCA12/IC2/IE3' => 'SMAPS individual',
                'SCA28/IC1/IE4' => 'Sensibilización VBG, DDSSRR y masculinidades',
            ],
        ],
        4 => [
            'name' => 'Registro civil e identidad',
            'description' => 'NNA con acceso al registro de nacimiento u otros documentos de identidad.',
            'indicators' => [
                'SCA18/IC1/IE2' => 'Registro civil de nacimientos',
                'SCA18/IC1/IE3' => 'Otros documentos, incluida la cédula',
            ],
        ],
        5 => [
            'name' => 'Cuidados alternativos y reunificación',
            'description' => 'NNA no acompañados o separados con cuidado alternativo, documentación y reunificación.',
            'indicators' => [
                'SCA13/IC1/IE1' => 'Cuidados alternativos basados en la familia',
                'SCA13/IC1/IE2' => 'Identificación, documentación y reunificación',
            ],
        ],
        6 => [
            'name' => 'Protección comunitaria e información',
            'description' => 'Población y organizaciones alcanzadas con formación e información sobre servicios de protección.',
            'indicators' => [
                'SCA24/IC1/IE3' => 'Comunidades formadas en protección y derivación',
                'SCA28/IC1/IE3' => 'Sesiones informativas sobre riesgos',
                'SCA33/IC1/IE3' => 'Organismos formados en protección y DDHH',
            ],
        ],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (self::GROUPS as $sortOrder => $definition) {
                $group = IndicatorGroup::updateOrCreate(
                    ['name' => $definition['name']],
                    [
                        'description' => $definition['description'],
                        'sort_order' => $sortOrder,
                    ],
                );

                foreach ($definition['indicators'] as $code => $shortName) {
                    $acceptedCodes = [$code, 'GCLPR/'.$code];

                    Indicador::query()
                        ->whereNull('indicator_group_id')
                        ->whereIn('codigo', $acceptedCodes)
                        ->update(['indicator_group_id' => $group->id]);

                    Indicador::query()
                        ->whereNull('nombre_corto')
                        ->whereIn('codigo', $acceptedCodes)
                        ->update(['nombre_corto' => $shortName]);
                }
            }
        });
    }
}
