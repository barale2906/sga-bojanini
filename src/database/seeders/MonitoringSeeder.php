<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Monitoring\Infrastructure\Persistence\Models\AlertRuleModel;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorModel;
use App\Modules\Monitoring\Infrastructure\Persistence\Models\SensorReadingModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use Illuminate\Database\Seeder;

class MonitoringSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = WarehouseModel::firstOrCreate(
            ['code' => 'ALM-PRINCIPAL'],
            [
                'name'        => 'Almacén Principal',
                'address'     => 'Sede principal',
                'description' => 'Almacén demo para monitoreo',
                'is_active'   => true,
            ],
        );

        $zone = ZoneModel::firstOrCreate(
            [
                'warehouse_id' => $warehouse->id,
                'code'         => 'ZR01',
            ],
            [
                'name'          => 'Zona Refrigerada 01',
                'type'          => 'cold',
                'temp_min'      => 2,
                'temp_max'      => 8,
                'humidity_min'  => 30,
                'humidity_max'  => 65,
                'description'   => 'Zona refrigerada para insumos sensibles',
                'is_active'     => true,
            ],
        );

        $tempSensor = SensorModel::firstOrCreate(
            ['code' => 'TEMP-ZR01-01'],
            [
                'zone_id'   => $zone->id,
                'name'      => 'Termómetro Zona Refrigerada',
                'type'      => 'temperature',
                'unit'      => '°C',
                'is_active' => true,
            ],
        );

        $humSensor = SensorModel::firstOrCreate(
            ['code' => 'HUM-ZR01-01'],
            [
                'zone_id'   => $zone->id,
                'name'      => 'Higrómetro Zona Refrigerada',
                'type'      => 'humidity',
                'unit'      => '%RH',
                'is_active' => true,
            ],
        );

        $now = now();
        $readings = [
            [$tempSensor->id, 4.5, $now->copy()->subHours(6)],
            [$tempSensor->id, 5.0, $now->copy()->subHours(4)],
            [$tempSensor->id, 4.8, $now->copy()->subHours(2)],
            [$tempSensor->id, 5.2, $now->copy()->subHour()],
            [$humSensor->id, 55.0, $now->copy()->subHours(6)],
            [$humSensor->id, 52.0, $now->copy()->subHours(4)],
            [$humSensor->id, 50.0, $now->copy()->subHours(2)],
            [$humSensor->id, 48.0, $now->copy()->subHour()],
        ];

        foreach ($readings as [$sensorId, $value, $recordedAt]) {
            SensorReadingModel::firstOrCreate(
                [
                    'sensor_id'   => $sensorId,
                    'recorded_at' => $recordedAt,
                ],
                [
                    'value'           => $value,
                    'reading_source'  => 'manual',
                    'user_id'         => null,
                ],
            );
        }

        AlertRuleModel::firstOrCreate(
            [
                'sensor_id'      => $tempSensor->id,
                'condition_type' => 'above',
            ],
            [
                'threshold'              => 7.5,
                'consecutive_readings'   => 2,
                'notification_channels'  => ['internal', 'email'],
                'is_active'              => true,
            ],
        );
    }
}
