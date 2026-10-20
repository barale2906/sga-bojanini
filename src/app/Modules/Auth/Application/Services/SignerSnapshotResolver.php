<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Services;

use App\Modules\Auth\Application\DTOs\SignerInput;
use App\Modules\Auth\Domain\Entities\UserSignature;

/**
 * Convierte la forma en que firma cada rol (entrega / recibe) en los datos
 * que se copian al documento firmado (snapshot).
 *
 * Es el punto único que usan los módulos que capturan firmas (Inventario,
 * Activos, …) para que las reglas sean idénticas en todos:
 *  - `stored`: firma registrada del usuario autenticado.
 *  - `pin`:    firma registrada del dueño del PIN (con rate limit por usuario que confirma).
 *  - `pad`:    nombre, documento e imagen capturados en el momento.
 */
class SignerSnapshotResolver
{
    public function __construct(
        private readonly RegisteredSignatureResolver $signatureResolver,
    ) {}

    /**
     * @param array<string, SignerInput> $signers rol => entrada (p. ej. delivered_by, received_by)
     * @param int $actingUserId usuario autenticado (dueño de la firma "stored" y llave del rate limit de PIN)
     *
     * @return array<string, array{method: string, user_id: int|null, user_signature_id: int|null, signer_name: string, signer_document: string, signature_data: string}>
     *
     * @throws \DomainException si entrega y recibe la misma persona
     * @throws \App\Modules\Auth\Domain\Exceptions\SignatureNotRegisteredException
     * @throws \App\Modules\Auth\Domain\Exceptions\InvalidSignaturePinException
     */
    public function resolveAll(array $signers, int $actingUserId): array
    {
        $rows = [];

        foreach ($signers as $role => $input) {
            $rows[$role] = $this->resolve($input, $actingUserId, "{$role}.pin");
        }

        $delivered = $rows['delivered_by']['user_id'] ?? null;
        $received  = $rows['received_by']['user_id'] ?? null;

        if ($delivered !== null && $delivered === $received) {
            throw new \DomainException('Quien recibe no puede ser la misma persona que entrega.');
        }

        return $rows;
    }

    /**
     * @return array{method: string, user_id: int|null, user_signature_id: int|null, signer_name: string, signer_document: string, signature_data: string}
     */
    public function resolve(SignerInput $input, int $actingUserId, string $pinField = 'received_by.pin'): array
    {
        return match ($input->method) {
            SignerInput::METHOD_STORED => $this->fromRegistered(
                SignerInput::METHOD_STORED,
                $this->signatureResolver->forUser($actingUserId),
            ),
            SignerInput::METHOD_PIN => $this->fromRegistered(
                SignerInput::METHOD_PIN,
                $this->signatureResolver->byPin((string) $input->pin, (string) $actingUserId, $pinField),
            ),
            default => [
                'method'            => SignerInput::METHOD_PAD,
                'user_id'           => null,
                'user_signature_id' => null,
                'signer_name'       => (string) $input->name,
                'signer_document'   => (string) $input->document,
                'signature_data'    => (string) $input->signature,
            ],
        };
    }

    /** @return array{method: string, user_id: int, user_signature_id: int, signer_name: string, signer_document: string, signature_data: string} */
    private function fromRegistered(string $method, UserSignature $signature): array
    {
        return [
            'method'            => $method,
            'user_id'           => $signature->getUserId(),
            'user_signature_id' => $signature->getId(),
            'signer_name'       => $signature->getSignerName(),
            'signer_document'   => $signature->getDocumentType()->value . ' ' . $signature->getDocumentNumber(),
            'signature_data'    => $signature->getSignatureData(),
        ];
    }
}
