<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Enums;

enum ReportExportStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}
