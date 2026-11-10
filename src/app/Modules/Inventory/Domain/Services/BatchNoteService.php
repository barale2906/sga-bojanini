<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Services;

use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Único punto de escritura de `batches.notes`.
 *
 * Cada movimiento puede dejar una nota opcional sobre el lote. Las notas se
 * acumulan: la más reciente va al principio y las anteriores se conservan
 * intactas debajo, nunca se sobrescriben. Cada línea indica fecha del
 * movimiento, tipo de movimiento y usuario:
 *
 *   [10/11/2026 · Salida · Nombre Apellido] Entregado a urgencias
 *
 * Debe invocarse dentro de la transacción del movimiento: bloquea la fila del
 * lote para que dos movimientos simultáneos no se pisen.
 */
class BatchNoteService
{
    /** Etiqueta legible de cada tipo de movimiento. */
    private const LABELS = [
        'entry'                => 'Entrada',
        'exit'                 => 'Salida',
        'transfer'             => 'Traslado',
        'adjustment'           => 'Ajuste',
        'loss'                 => 'Pérdida',
        'return'               => 'Devolución',
        'expiration_write_off' => 'Baja por vencimiento',
    ];

    public function append(
        int $batchId,
        string $movementType,
        ?string $note,
        ?int $userId,
        CarbonInterface|string|null $movementDate = null,
    ): void {
        $note = $note === null ? '' : trim($note);

        if ($note === '') {
            return;
        }

        $batch = BatchModel::query()->lockForUpdate()->findOrFail($batchId);

        $batch->notes = $this->prepend($batch->notes, $this->formatLine($movementType, $note, $userId, $movementDate));
        $batch->save();
    }

    private function prepend(?string $existing, string $line): string
    {
        $existing = $existing === null ? '' : trim($existing);

        return $existing === '' ? $line : $line."\n".$existing;
    }

    private function formatLine(string $movementType, string $note, ?int $userId, CarbonInterface|string|null $date): string
    {
        $when  = ($date === null ? Carbon::now() : Carbon::parse($date))->format('d/m/Y');
        $label = self::LABELS[$movementType] ?? $movementType;
        $who   = $userId === null ? null : UserModel::query()->whereKey($userId)->value('name');

        $header = implode(' · ', array_filter([$when, $label, $who]));

        return "[{$header}] {$note}";
    }
}
