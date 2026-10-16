<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence;

use App\Modules\Auth\Domain\Entities\UserSignature;
use App\Modules\Auth\Domain\Repositories\UserSignatureRepositoryInterface;
use App\Modules\Auth\Domain\ValueObjects\IdentityDocumentType;
use App\Modules\Auth\Infrastructure\Persistence\Models\RetiredSignaturePinModel;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserSignatureModel;
use DateTimeImmutable;

class EloquentUserSignatureRepository implements UserSignatureRepositoryInterface
{
    public function findActiveByUser(int $userId): ?UserSignature
    {
        $model = UserSignatureModel::with('user')
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findActiveByPinLookup(string $pinLookup): ?UserSignature
    {
        $model = UserSignatureModel::with('user')
            ->where('pin_lookup', $pinLookup)
            ->where('is_active', true)
            ->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function pinLookupExists(string $pinLookup): bool
    {
        return UserSignatureModel::where('pin_lookup', $pinLookup)->exists()
            || RetiredSignaturePinModel::where('pin_lookup', $pinLookup)
                ->where('available_at', '>', now())
                ->exists();
    }

    public function registerVersion(
        int $userId,
        IdentityDocumentType $documentType,
        string $documentNumber,
        string $signatureData,
        ?string $pinLookup,
    ): UserSignature {
        $previous = UserSignatureModel::where('user_id', $userId)
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();

        // El HMAC tiene índice único: se libera de la versión anterior antes
        // de asignarlo a la nueva.
        $previous?->update([
            'is_active'      => false,
            'deactivated_at' => now(),
            'pin_lookup'     => null,
        ]);

        $model = UserSignatureModel::create([
            'user_id'          => $userId,
            'document_type'    => $documentType,
            'document_number'  => $documentNumber,
            'signature_data'   => $signatureData,
            'pin_lookup'       => $pinLookup,
            'pin_generated_at' => $pinLookup === null ? null : ($previous?->pin_generated_at ?? now()),
            'is_active'        => true,
        ]);

        return $this->toEntity($model->load('user'));
    }

    public function findActivePinLookup(int $userId): ?string
    {
        return UserSignatureModel::where('user_id', $userId)
            ->where('is_active', true)
            ->value('pin_lookup');
    }

    public function replacePinLookup(int $userId, string $pinLookup, \DateTimeInterface $oldPinAvailableAt): UserSignature
    {
        $model = UserSignatureModel::where('user_id', $userId)
            ->where('is_active', true)
            ->lockForUpdate()
            ->firstOrFail();

        if ($model->pin_lookup !== null) {
            RetiredSignaturePinModel::create([
                'pin_lookup'   => $model->pin_lookup,
                'user_id'      => $userId,
                'retired_at'   => now(),
                'available_at' => $oldPinAvailableAt,
            ]);
        }

        $model->update([
            'pin_lookup'       => $pinLookup,
            'pin_generated_at' => now(),
        ]);

        return $this->toEntity($model->load('user'));
    }

    private function toEntity(UserSignatureModel $model): UserSignature
    {
        return new UserSignature(
            id: $model->id,
            userId: $model->user_id,
            signerName: (string) $model->user?->name,
            documentType: $model->document_type,
            documentNumber: $model->document_number,
            signatureData: $model->signature_data,
            hasPin: $model->pin_lookup !== null,
            pinGeneratedAt: $model->pin_generated_at ? DateTimeImmutable::createFromInterface($model->pin_generated_at) : null,
            registeredAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
