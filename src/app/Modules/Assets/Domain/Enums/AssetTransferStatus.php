<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Enums;

enum AssetTransferStatus: string
{
    case PendingSignature = 'pending_signature';
    case Confirmed        = 'confirmed';
    case Cancelled        = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingSignature => 'Pendiente de firma',
            self::Confirmed        => 'Confirmado',
            self::Cancelled        => 'Cancelado',
        };
    }
}
