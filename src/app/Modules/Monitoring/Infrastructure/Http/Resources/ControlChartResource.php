<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ControlChartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'sensor_id'               => $this->resource['sensor_id'],
            'date_from'               => $this->resource['date_from'],
            'date_to'                 => $this->resource['date_to'],
            'total_readings'          => $this->resource['total_readings'],
            'mean'                    => $this->resource['mean'],
            'std_dev'                 => $this->resource['std_dev'],
            'ucl'                     => $this->resource['ucl'],
            'lcl'                     => $this->resource['lcl'],
            'uwl'                     => $this->resource['uwl'],
            'lwl'                     => $this->resource['lwl'],
            'cp'                      => $this->resource['cp'],
            'cpk'                     => $this->resource['cpk'],
            'process_capable'         => $this->resource['process_capable'],
            'out_of_control_count'    => $this->resource['out_of_control_count'],
            'out_of_control_readings' => $this->resource['out_of_control_readings'],
            'readings'                => $this->resource['readings'],
        ];
    }
}
