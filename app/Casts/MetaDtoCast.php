<?php

namespace App\Casts;

use App\Dto\MetaDto;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

final class MetaDtoCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?MetaDto
    {
        if ($value instanceof MetaDto) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? MetaDto::fromArray($decoded) : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $array = match (true) {
            $value instanceof MetaDto => $value->toArray(),
            is_array($value) => $value,
            default => null,
        };

        return [
            $key => $array === null ? null : json_encode($array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }
}
