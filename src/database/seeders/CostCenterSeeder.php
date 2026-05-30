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
            ['code' => 'FARM',  'name' => 'Farmacia',                  'description' => 'Dispensación interna de medicamentos e insumos'],
            ['code' => 'ADMIN', 'name' => 'Administración',            'description' => 'Consumo de insumos área administrativa'],
            ['code' => 'LAB',   'name' => 'Laboratorio',               'description' => 'Insumos para análisis y diagnóstico'],
            ['code' => 'MANT',  'name' => 'Mantenimiento',             'description' => 'Insumos para mantenimiento de equipos e instalaciones'],
            ['code' => 'ENFER', 'name' => 'Enfermería',                'description' => 'Insumos de enfermería de uso interno'],
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

        // ─── Centros de costo externos (pacientes) ────────────────────────────────
        $externalCenters = [
            ['code' => 'PAC-AMB', 'name' => 'Pacientes Ambulatorios', 'description' => 'Consumo en atención ambulatoria'],
            ['code' => 'PAC-HOSP','name' => 'Pacientes Hospitalizados','description' => 'Consumo en hospitalización'],
            ['code' => 'PAC-URG', 'name' => 'Pacientes Urgencias',    'description' => 'Consumo en urgencias y emergencias'],
            ['code' => 'PAC-CIR', 'name' => 'Pacientes Cirugía',      'description' => 'Consumo en sala de cirugía'],
        ];

        foreach ($externalCenters as $data) {
            CostCenterModel::firstOrCreate(
                ['code' => $data['code']],
                [
                    'name'        => $data['name'],
                    'type'        => 'external',
                    'description' => $data['description'],
                    'is_active'   => true,
                ],
            );
        }

        // ─── Servicios médicos ────────────────────────────────────────────────────
        $services = [
            ['code' => 'CONS-MED', 'name' => 'Consulta Médica General',   'description' => 'Atención médica ambulatoria general'],
            ['code' => 'CONS-ESP', 'name' => 'Consulta Especialista',      'description' => 'Atención médica por especialista'],
            ['code' => 'CIR-GEN',  'name' => 'Cirugía General',            'description' => 'Procedimientos quirúrgicos generales'],
            ['code' => 'CIR-LAP',  'name' => 'Cirugía Laparoscópica',      'description' => 'Procedimientos laparoscópicos'],
            ['code' => 'HOSP-MED', 'name' => 'Hospitalización Médica',     'description' => 'Internación en piso médico'],
            ['code' => 'HOSP-CIR', 'name' => 'Hospitalización Quirúrgica', 'description' => 'Internación post-quirúrgica'],
            ['code' => 'URG-BAJA', 'name' => 'Urgencias Baja Complejidad', 'description' => 'Urgencias de baja complejidad'],
            ['code' => 'URG-ALTA', 'name' => 'Urgencias Alta Complejidad', 'description' => 'Urgencias y emergencias de alta complejidad'],
            ['code' => 'UCI',      'name' => 'UCI / Cuidados Intensivos',  'description' => 'Unidad de cuidados intensivos'],
            ['code' => 'LAB-CLIN', 'name' => 'Laboratorio Clínico',        'description' => 'Toma de muestras y análisis laboratorio'],
            ['code' => 'IMAGEN',   'name' => 'Imagenología',               'description' => 'Radiología, ecografía y diagnóstico por imagen'],
            ['code' => 'REHAB',    'name' => 'Rehabilitación',             'description' => 'Fisioterapia y rehabilitación funcional'],
        ];

        foreach ($services as $data) {
            MedicalServiceModel::firstOrCreate(
                ['code' => $data['code']],
                [
                    'name'        => $data['name'],
                    'description' => $data['description'],
                    'is_active'   => true,
                ],
            );
        }

        $this->command->info('Centros de costo y servicios médicos creados exitosamente.');
    }
}
