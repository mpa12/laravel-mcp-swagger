<?php

namespace App\Dto;

final class PetStoreOperationDetailsDto
{
    public function __construct(
        public readonly string $operationId,
        public readonly string $method,
        public readonly string $path,
    ) {}

    public static function fromArray(array $validated): self
    {
        return new self(
            operationId: trim((string) ($validated['operationId'] ?? '')),
            method: strtoupper(trim((string) ($validated['method'] ?? ''))),
            path: trim((string) ($validated['path'] ?? '')),
        );
    }
}
