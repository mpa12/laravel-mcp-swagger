<?php

namespace App\Dto;

final class PetStoreSearchDto
{
    private const DEFAULT_LIMIT = 10;

    public function __construct(
        public readonly string $query,
        public readonly ?string $type,
        public readonly ?string $method,
        public readonly ?string $path,
        public readonly int $limit,
    ) {}

    public static function fromArray(array $validated): self
    {
        return new self(
            query: trim((string) ($validated['query'] ?? '')),
            type: isset($validated['type']) ? (string) $validated['type'] : null,
            method: isset($validated['method']) ? strtoupper(trim((string) $validated['method'])) : null,
            path: isset($validated['path']) ? trim((string) $validated['path']) : null,
            limit: (int) ($validated['limit'] ?? self::DEFAULT_LIMIT),
        );
    }
}
