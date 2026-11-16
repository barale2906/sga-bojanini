<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Application\DTOs;

use Illuminate\Http\UploadedFile;

class CalibrationCertificateData
{
    public function __construct(
        public readonly UploadedFile $file,
        public readonly string $expiresAt,
        public readonly ?string $calibratedAt = null,
        public readonly ?string $certificateNumber = null,
        public readonly ?string $laboratory = null,
        public readonly ?string $notes = null,
    ) {}
}
