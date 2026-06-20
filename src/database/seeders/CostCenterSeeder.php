<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\CostCenter\Infrastructure\Persistence\Models\CostCenterModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use Illuminate\Database\Seeder;

/**
 * Seeder para centros de costo y servicios médicos iniciales.
 *
 * Ejecutar: php artisan db:seed --class=CostCenterSeeder
 */
class CostCenterSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Centros de costo internos ────────────────────────────────────────────
        $internalCenters = [
            ['code' => 'ENF',  'name' => 'Enfermería',       'description' => 'Insumos para enfermería'],
            ['code' => 'ANTI-ENV', 'name' => 'Antienvejecimiento', 'description' => 'Insumos para antienvejecimiento'],
            ['code' => 'FAC',   'name' => 'Facial',    'description' => 'Insumos para facial'],
            ['code' => 'CORPO',  'name' => 'Corporal',  'description' => 'Insumos para Corporal'],
            ['code' => 'COOLTECH', 'name' => 'COOLTECH',     'description' => 'Insumos para Cooltech'],
            ['code' => 'DEP-LAS', 'name' => 'Depilación Laser',     'description' => 'Insumos para depilación laser'],
            ['code' => 'CLIN', 'name' => 'Clínica',     'description' => 'Insumos para clínica'],
            ['code' => 'IMPL-CAPI', 'name' => 'implante capilar',     'description' => 'Insumos para implante capilar'],
        ];

        foreach ($internalCenters as $data) {
            CostCenterModel::firstOrCreate(
                ['code' => $data['code']],
                [
                    'name'        => $data['name'],
                    'type'        => 'internal',
                    'description' => $data['description'],
                    'is_active'   => true,
                ],
            );
        }

        // ─── Centro de costo externo (paciente) ───────────────────────────────────
        CostCenterModel::firstOrCreate(
            ['code' => 'PAC'],
            [
                'name'        => 'Paciente',
                'type'        => 'external',
                'description' => 'Consumo de insumos facturado directamente al paciente',
                'is_active'   => true,
            ],
        );

        /*
        // ─── Servicios médicos (nodos raíz, type = service) ───────────────────────
        $services = [
            ['code' => 'CONS',  'name' => 'Consulta Médica',  'description' => 'Consultas médicas generales y de especialidad'],
            ['code' => 'CIR',   'name' => 'Cirugía',          'description' => 'Procedimientos quirúrgicos'],
            ['code' => 'HOSP',  'name' => 'Hospitalización',  'description' => 'Internación en piso médico o quirúrgico'],
            ['code' => 'URG',   'name' => 'Urgencias',        'description' => 'Urgencias y emergencias'],
            ['code' => 'UCI',   'name' => 'UCI',              'description' => 'Unidad de cuidados intensivos'],
            ['code' => 'LAB-C', 'name' => 'Laboratorio Clínico', 'description' => 'Toma de muestras y análisis'],
            ['code' => 'IMAGEN','name' => 'Imagenología',     'description' => 'Radiología, ecografía y diagnóstico por imagen'],
            ['code' => 'REHAB', 'name' => 'Rehabilitación',   'description' => 'Fisioterapia y rehabilitación funcional'],
        ];

        $procedures = [
            'CONS' => [
                ['code' => 'CONS-GEN',  'name' => 'Consulta General',           'description' => 'Consulta médica general'],
                ['code' => 'CONS-PED',  'name' => 'Consulta Pediátrica',        'description' => 'Consulta médica en pediatría'],
                ['code' => 'CONS-GIN',  'name' => 'Consulta Ginecológica',      'description' => 'Consulta médica en ginecología'],
                ['code' => 'CONS-CAR',  'name' => 'Consulta Cardiológica',      'description' => 'Consulta médica en cardiología'],
            ],
            'CIR' => [
                ['code' => 'CIR-LAP',   'name' => 'Cirugía Laparoscópica',      'description' => 'Procedimiento quirúrgico por laparoscopía'],
                ['code' => 'CIR-APEN',  'name' => 'Apendicectomía',             'description' => 'Extirpación del apéndice'],
                ['code' => 'CIR-HER',   'name' => 'Hernioplastia',              'description' => 'Corrección quirúrgica de hernia'],
                ['code' => 'CIR-COLE',  'name' => 'Colecistectomía',            'description' => 'Extirpación de la vesícula biliar'],
            ],
            'HOSP' => [
                ['code' => 'HOSP-MED',  'name' => 'Hospitalización Médica',     'description' => 'Internación en piso médico general'],
                ['code' => 'HOSP-QX',   'name' => 'Hospitalización Quirúrgica', 'description' => 'Internación en piso quirúrgico'],
                ['code' => 'HOSP-OBS',  'name' => 'Hospitalización Obstétrica', 'description' => 'Internación en sala de obstetricia'],
            ],
            'URG' => [
                ['code' => 'URG-TRIA',  'name' => 'Triage',                     'description' => 'Clasificación y evaluación inicial de urgencias'],
                ['code' => 'URG-ATEN',  'name' => 'Atención de Urgencia',       'description' => 'Atención médica urgente'],
                ['code' => 'URG-REANI', 'name' => 'Reanimación',                'description' => 'Maniobras de reanimación cardiopulmonar'],
                ['code' => 'URG-CUR',   'name' => 'Curación de Heridas',        'description' => 'Limpieza y curación de heridas en urgencias'],
            ],
            'UCI' => [
                ['code' => 'UCI-ADULT', 'name' => 'UCI Adultos',                'description' => 'Cuidados intensivos en paciente adulto'],
                ['code' => 'UCI-NEO',   'name' => 'UCI Neonatal',               'description' => 'Cuidados intensivos en neonatos'],
                ['code' => 'UCI-PED',   'name' => 'UCI Pediátrica',             'description' => 'Cuidados intensivos en paciente pediátrico'],
            ],
            'LAB-C' => [
                ['code' => 'LAB-HEM',   'name' => 'Hemograma',                  'description' => 'Cuadro hemático completo'],
                ['code' => 'LAB-GLU',   'name' => 'Glucemia',                   'description' => 'Medición de glucosa en sangre'],
                ['code' => 'LAB-PCR',   'name' => 'PCR',                        'description' => 'Proteína C reactiva'],
                ['code' => 'LAB-URI',   'name' => 'Uroanálisis',                'description' => 'Análisis físico-químico de orina'],
                ['code' => 'LAB-CULT',  'name' => 'Cultivo',                    'description' => 'Cultivo microbiológico de muestra biológica'],
            ],
            'IMAGEN' => [
                ['code' => 'IMG-RX',    'name' => 'Radiografía',                'description' => 'Radiografía convencional'],
                ['code' => 'IMG-ECO',   'name' => 'Ecografía',                  'description' => 'Ultrasonido diagnóstico'],
                ['code' => 'IMG-TAC',   'name' => 'Tomografía (TAC)',           'description' => 'Tomografía axial computarizada'],
                ['code' => 'IMG-RMN',   'name' => 'Resonancia Magnética',       'description' => 'Resonancia magnética nuclear'],
            ],
            'REHAB' => [
                ['code' => 'REH-FIS',   'name' => 'Fisioterapia',               'description' => 'Sesión de fisioterapia convencional'],
                ['code' => 'REH-HIDRO', 'name' => 'Hidroterapia',               'description' => 'Terapia en agua'],
                ['code' => 'REH-OCUP',  'name' => 'Terapia Ocupacional',        'description' => 'Rehabilitación mediante actividades funcionales'],
                ['code' => 'REH-FONO',  'name' => 'Fonoaudiología',             'description' => 'Terapia de lenguaje y deglución'],
            ],
        ];


        foreach ($services as $data) {
            $service = MedicalServiceModel::firstOrCreate(
                ['code' => $data['code']],
                [
                    'parent_id'   => null,
                    'type'        => 'service',
                    'name'        => $data['name'],
                    'description' => $data['description'],
                    'is_active'   => true,
                ],
            );

            foreach ($procedures[$data['code']] ?? [] as $proc) {
                MedicalServiceModel::firstOrCreate(
                    ['code' => $proc['code']],
                    [
                        'parent_id'   => $service->id,
                        'type'        => 'procedure',
                        'name'        => $proc['name'],
                        'description' => $proc['description'],
                        'is_active'   => true,
                    ],
                );
            }
        }
        */
        $this->command->info('Centros de costo y servicios médicos creados exitosamente.');
    }
}
