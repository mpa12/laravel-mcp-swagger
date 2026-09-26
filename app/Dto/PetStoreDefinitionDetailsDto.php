<?php

namespace App\Dto;

final class PetStoreDefinitionDetailsDto
{
    public function __construct(
        public readonly string $name,
    ) {}

    public static function fromArray(array $validated): self
    {
        return new self(
            name: trim((string) ($validated['name'] ?? '')),
        );
    }
}
