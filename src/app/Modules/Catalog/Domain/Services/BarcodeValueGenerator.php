<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Services;

class BarcodeValueGenerator
{
    public function generate(int $maxExisting): string
    {
        return str_pad((string) ($maxExisting + 1), 6, '0', STR_PAD_LEFT);
    }
}
