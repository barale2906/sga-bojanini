<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases;

use App\Modules\Purchasing\Domain\Services\ReorderPointCalculator;

class GenerateReorderSuggestionsUseCase
{
    public function __construct(
        private readonly ReorderPointCalculator $calculator,
    ) {}

    public function execute(): array
    {
        return $this->calculator->generateSuggestions();
    }
}
