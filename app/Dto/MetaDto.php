<?php

namespace App\Dto;

final class MetaDto
{
    public function __construct(
        public readonly array $tags,
    ) {}

    public static function fromArray(array $meta): self
    {
        $tags = $meta['tags'] ?? [];

        return new self(
            tags: is_array($tags) ? array_values($tags) : [],
        );
    }

    public function toArray(): array
    {
        return ['tags' => $this->tags];
    }
}
