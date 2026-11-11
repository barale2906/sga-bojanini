<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases;

use App\Modules\Purchasing\Domain\Services\ReorderPointCalculator;
use App\Modules\Purchasing\Infrastructure\Notifications\ReorderSuggestionsNotification;
use App\Modules\Shared\Application\Services\NotificationRecipientService;

/**
 * Revisión diaria de necesidad de compra: envía UN resumen consolidado con
 * los productos bajo su punto de reorden. Si no hay sugerencias no notifica.
 * Es independiente del aviso inmediato StockBelowReorderPoint.
 */
class NotifyReorderSuggestionsUseCase
{
    public const NOTIFICATION_TYPE = 'reorder_suggestions';

    public function __construct(
        private readonly ReorderPointCalculator $calculator,
        private readonly NotificationRecipientService $recipients,
    ) {}

    /** @return array{products: int} */
    public function execute(): array
    {
        $suggestions = $this->calculator->generateSuggestions();

        if ($suggestions !== []) {
            $this->recipients->notifyByType(
                self::NOTIFICATION_TYPE,
                new ReorderSuggestionsNotification($suggestions),
            );
        }

        return ['products' => count($suggestions)];
    }
}
