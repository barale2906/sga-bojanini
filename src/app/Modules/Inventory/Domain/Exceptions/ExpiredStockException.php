<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Exceptions;

/**
 * Se lanza cuando el producto tiene stock registrado en el almacén, pero
 * la totalidad (o el remanente requerido) corresponde a lotes vencidos.
 *
 * El stock vencido no puede salir por movimientos de salida/transferencia;
 * debe gestionarse mediante devolución, ajuste o baja por vencimiento.
 */
class ExpiredStockException extends \DomainException
{
}
