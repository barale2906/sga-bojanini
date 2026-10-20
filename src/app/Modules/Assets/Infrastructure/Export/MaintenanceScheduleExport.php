<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Export;

use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMaintenanceModel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Cronograma de mantenimiento en Excel. */
class MaintenanceScheduleExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
{
    /** @param Collection<int, AssetMaintenanceModel> $maintenances */
    public function __construct(
        private readonly Collection $maintenances,
    ) {}

    public function collection(): Collection
    {
        return $this->maintenances;
    }

    public function title(): string
    {
        return 'Cronograma';
    }

    public function headings(): array
    {
        return [
            'Fecha programada', 'Código activo', 'Activo', 'Ubicación', 'Tipo', 'Estado', 'Origen',
            'Ejecutado', 'Ejecutor', 'Resultado', 'Registrado por',
        ];
    }

    /** @param AssetMaintenanceModel $m */
    public function map($m): array
    {
        $performer = match ($m->performed_by_type?->value) {
            'internal' => $m->technician?->name,
            'external' => trim(($m->supplier?->name ?? '') . ' — ' . ($m->external_technician_name ?? ''), ' —'),
            default    => null,
        };

        return [
            $m->scheduled_date?->toDateString(),
            $m->asset?->code,
            $m->asset?->name,
            $m->asset?->currentPlace?->fullName() ?? $m->asset?->off_premises_location,
            $m->maintenance_type->label(),
            $m->status->label(),
            $m->plan_id ? 'Plan' : 'Puntual',
            $m->performed_at?->format('Y-m-d H:i'),
            $performer,
            $m->result?->label(),
            $m->registeredBy?->name,
        ];
    }
}
