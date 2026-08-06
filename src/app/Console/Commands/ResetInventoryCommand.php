<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetInventoryCommand extends Command
{
    protected $signature = 'sga:reset-inventory {--force : Omitir la confirmación interactiva}';
    protected $description = 'Borra todo el inventario: movimientos, lotes, stock. Deja el catálogo intacto.';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->warn('⚠  Esta operación elimina TODOS los movimientos, lotes y stock de inventario.');
            $this->warn('   El catálogo de productos y almacenes NO se verá afectado.');

            if (! $this->confirm('¿Desea continuar?', false)) {
                $this->info('Operación cancelada.');
                return self::SUCCESS;
            }
        }

        $this->info('Limpiando inventario...');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            DB::table('movement_signatures')->truncate();
            $this->line('  ✓ movement_signatures');

            DB::table('stock_movements')->truncate();
            $this->line('  ✓ stock_movements');

            DB::table('movement_documents')->truncate();
            $this->line('  ✓ movement_documents');

            DB::table('batch_location')->truncate();
            $this->line('  ✓ batch_location');

            DB::table('batches')->truncate();
            $this->line('  ✓ batches');

            DB::table('stock_summaries')->truncate();
            $this->line('  ✓ stock_summaries');

            DB::table('kit_transactions')->truncate();
            $this->line('  ✓ kit_transactions');

            // Desvincula registros de pacientes del documento de movimiento eliminado
            DB::table('patient_procedure_records')->update(['movement_document_id' => null]);
            $this->line('  ✓ patient_procedure_records.movement_document_id → NULL');
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->info('Inventario reseteado correctamente.');

        return self::SUCCESS;
    }
}
