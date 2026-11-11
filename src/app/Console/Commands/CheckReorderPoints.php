<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Purchasing\Application\UseCases\NotifyReorderSuggestionsUseCase;
use Illuminate\Console\Command;

class CheckReorderPoints extends Command
{
    protected $signature = 'sga:check-reorder-points';

    protected $description = 'Notifica un resumen de productos que necesitan reorden (necesidad de compra)';

    public function handle(NotifyReorderSuggestionsUseCase $useCase): int
    {
        $result = $useCase->execute();

        $this->info("Productos que necesitan reorden: {$result['products']}");

        return self::SUCCESS;
    }
}
