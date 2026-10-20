<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\DTOs;

use Illuminate\Http\UploadedFile;

readonly class AssetDocumentData
{
    public function __construct(
        public UploadedFile $file,
        public string $documentType,
        public string $title,
        public ?int $maintenanceId = null,
        public ?string $issuedAt = null,
        public ?string $expiresAt = null,
    ) {}
}
