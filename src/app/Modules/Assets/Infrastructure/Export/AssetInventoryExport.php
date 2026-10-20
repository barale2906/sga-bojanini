<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Export;

use App\Modules\Assets\Infrastructure\Persistence\Models\AssetModel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Inventario de activos en Excel. */
class AssetInventoryExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
{
    /** @param Collection<int, AssetModel> $assets */
    public function __construct(
        private readonly Collection $assets,
    ) {}

    public function collection(): Collection
    {
        return $this->assets;
    }

    public function title(): string
    {
        return 'Inventario de activos';
    }

    public function headings(): array
    {
        return [
            'Código', 'Nombre', 'Categoría', 'Tipo', 'Estado', 'Sede', 'Sitio', 'Fuera de instalaciones',
            'Responsable', 'Marca', 'Modelo', 'Serial', 'Fecha adquisición', 'Valor adquisición',
            'Proveedor', 'Factura', 'Garantía hasta', 'Próximo mantenimiento',
        ];
    }

    /** @param AssetModel $asset */
    public function map($asset): array
    {
        return [
            $asset->code,
            $asset->name,
            $asset->category?->name,
            $asset->category?->asset_type->label(),
            $asset->status->label(),
            $asset->currentPlace?->site?->name,
            $asset->currentPlace?->name,
            $asset->is_off_premises ? ($asset->off_premises_location ?? 'Sí') : 'No',
            $asset->responsible?->name,
            $asset->brand,
            $asset->model,
            $asset->serial,
            $asset->acquisition_date?->toDateString(),
            $asset->acquisition_value,
            $asset->supplier?->name,
            $asset->invoice_number,
            $asset->warranty_until?->toDateString(),
            $asset->nextMaintenance?->scheduled_date?->toDateString(),
        ];
    }
}
