<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Services;

use App\Modules\Shared\Domain\Services\SequentialCodeGenerator;

/**
 * Código de barras interno de los productos genéricos (`000001`–`999999`).
 */
class BarcodeValueGenerator extends SequentialCodeGenerator
{
}
