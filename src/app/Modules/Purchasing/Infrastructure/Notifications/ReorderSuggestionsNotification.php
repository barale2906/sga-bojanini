<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Notifications;

use App\Modules\Shared\Infrastructure\Notifications\Concerns\UsesSgaChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Resumen diario de productos que necesitan reposición (necesidad de compra). */
class ReorderSuggestionsNotification extends Notification
{
    use Queueable;
    use UsesSgaChannels;

    /** Máximo de líneas en el mensaje (el resto se resume). */
    private const MAX_LINES = 20;

    /**
     * @param array<int, array<string, mixed>> $suggestions Salida de ReorderPointCalculator::generateSuggestions()
     */
    public function __construct(
        private readonly array $suggestions,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->sgaChannels();
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'     => 'reorder_suggestions',
            'title'    => 'Productos por reabastecer',
            'message'  => count($this->suggestions) . ' producto(s) necesitan reposición.',
            'items'    => array_slice($this->lines(), 0, self::MAX_LINES),
            'severity' => 'warning',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Necesidad de compra: ' . count($this->suggestions) . ' producto(s) por reabastecer')
            ->greeting('Productos por reabastecer');

        $lines = $this->lines();

        foreach (array_slice($lines, 0, self::MAX_LINES) as $line) {
            $mail->line("• {$line}");
        }

        if (count($lines) > self::MAX_LINES) {
            $mail->line('… y ' . (count($lines) - self::MAX_LINES) . ' más.');
        }

        return $mail;
    }

    /** @return string[] */
    private function lines(): array
    {
        return array_map(function (array $s): string {
            $supplier = $s['preferred_supplier']['name'] ?? null;

            return sprintf(
                '%s (%s) — stock %d, consumo diario %s, sugerido comprar %d%s',
                $s['product_name'],
                $s['product_barcode'] ?? 'sin código',
                $s['current_stock'],
                rtrim(rtrim(number_format((float) $s['daily_consumption'], 2, '.', ''), '0'), '.') ?: '0',
                $s['suggested_quantity'],
                $supplier ? " — proveedor: {$supplier}" : '',
            );
        }, array_values($this->suggestions));
    }
}
