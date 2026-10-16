<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Exceptions;

/**
 * Se lanza cuando un movimiento intenta afectar una ubicación que no
 * pertenece al almacén del movimiento. Un movimiento solo puede tocar
 * ubicaciones (de cualquier zona o estante) de su propio almacén.
 */
class LocationOutsideWarehouseException extends \DomainException
{
}
