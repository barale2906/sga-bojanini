<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    /** [nombre, código almacén, prefijo zona] */
    private const WAREHOUSES = [
        // ── Inyectables ───────────────────────────────────────────────────────
        ['Antienvejecimiento',      'ANT',     'ANT'],
        ['Botox',                   'BOT',     'BOT'],
        ['Colorscience',            'COL',     'COL'],
        ['Dermazoom',               'DER',     'DER'],
        ['Enzimas',                 'ENZ',     'ENZ'],
        ['Facial',                  'FAC',     'FAC'],
        ['LaserMed',                'LAS',     'LAS'],
        ['Liftera',                 'LIF',     'LIF'],
        ['Metabolismo',             'MET',     'MET'],
        ['Productos Dermatologicos','PRO_DER', 'PRO_DER'],
        ['Ultherapy',               'ULT',     'ULT'],
        ['ZO',                      'ZO',      'ZO'],
        // ── Insumos Médicos ───────────────────────────────────────────────────
        ['Clinica',                 'CLI',     'CLI'],
        ['Cooltech',                'COO',     'COO'],
        ['Corporal',                'COR',     'COR'],
        ['Depilacion Laser',        'DEP_LAS', 'DEP_LAS'],
        ['Enfermeria',              'ENF',     'ENF'],
        ['Implante Capilar',        'IMP_CAP', 'IMP_CAP'],
    ];

    public function run(): void
    {
        foreach (self::WAREHOUSES as [$name, $code, $prefix]) {
            $warehouse = WarehouseModel::firstOrCreate(
                ['code' => $code],
                [
                    'name'      => $name,
                    'is_active' => true,
                ]
            );

            ZoneModel::firstOrCreate(
                [
                    'warehouse_id' => $warehouse->id,
                    'code'         => $prefix . '_AMB',
                ],
                [
                    'name'      => $prefix . ' Ambiental',
                    'type'      => 'ambient',
                    'is_active' => true,
                ]
            );
        }
    }
}
