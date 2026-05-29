<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductClassificationModel;
use Illuminate\Database\Seeder;

class ProductClassificationSeeder extends Seeder
{
    public function run(): void
    {
        $classifications = [
            [
                'code'                      => 'MED',
                'name'                      => 'Medicamento',
                'description'               => 'Productos farmacéuticos destinados al diagnóstico, tratamiento o prevención de enfermedades.',
                'has_sanitary_registration' => true,
                'has_concentration'         => true,
                'has_risk_level'            => false,
                'has_pharma_fields'         => true,
                'has_device_fields'         => false,
                'has_lab_brand'             => true,
                'is_active'                 => true,
            ],
            [
                'code'                      => 'DM',
                'name'                      => 'Dispositivo Médico',
                'description'               => 'Equipos, instrumentos, materiales u otros artículos de uso médico, incluidos los programas informáticos.',
                'has_sanitary_registration' => true,
                'has_concentration'         => false,
                'has_risk_level'            => true,
                'has_pharma_fields'         => false,
                'has_device_fields'         => true,
                'has_lab_brand'             => true,
                'is_active'                 => true,
            ],
            [
                'code'                      => 'OTR',
                'name'                      => 'Otro',
                'description'               => 'Productos de uso hospitalario que no corresponden a medicamentos ni dispositivos médicos.',
                'has_sanitary_registration' => false,
                'has_concentration'         => false,
                'has_risk_level'            => false,
                'has_pharma_fields'         => false,
                'has_device_fields'         => false,
                'has_lab_brand'             => false,
                'is_active'                 => true,
            ],
        ];

        foreach ($classifications as $data) {
            ProductClassificationModel::firstOrCreate(
                ['code' => $data['code']],
                $data
            );
        }
    }
}
