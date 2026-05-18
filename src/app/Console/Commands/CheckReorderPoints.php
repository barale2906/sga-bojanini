<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Purchasing\Domain\Services\ReorderPointCalculator;
use Illuminate\Console\Command;

class CheckReorderPoints extends Command
{
    protected $signature = 'sga:check-reorder-points';

    protected $description = 'Verifica productos que necesitan reorden y genera sugerencias';

    public function handle(ReorderPointCalculator $calculator): int
    {
        $suggestions = $calculator->generateSuggestions();
        $this->info('Productos que necesitan reorden: '.count($suggestions));

        foreach ($suggestions as $suggestion) {
            $this->line(sprintf(
                '  - %s (%s): sugerir comprar %d unidades',
                $suggestion['product_name'],
                $suggestion['product_code'],
                $suggestion['suggested_quantity'],
            ));
        }

        return self::SUCCESS;
    }
}
