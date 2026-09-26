<?php

namespace App\Dto;

final class PetStoreTagDetailsDto
{
    public function __construct(
        public readonly string $tag,
    ) {}

    public static function fromArray(array $validated): self
    {
        return new self(
            tag: trim((string) ($validated['tag'] ?? '')),
        );
    }
}
