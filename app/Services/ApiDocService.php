<?php

namespace App\Services;

use App\Dto\PetStoreDefinitionDetailsDto;
use App\Dto\PetStoreOperationDetailsDto;
use App\Dto\PetStoreSearchDto;
use App\Dto\PetStoreTagDetailsDto;
use App\Models\ApiDoc;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ApiDocService
{
    public function search(PetStoreSearchDto $data): Collection
    {
        return ApiDoc::search($data->query)
            ->take($data->limit)
            ->when($data->type, fn (\Laravel\Scout\Builder $query) => $query->where('type', $data->type))
            ->when($data->method, fn (\Laravel\Scout\Builder $query) => $query->where('method', $data->method))
            ->when($data->path, fn (\Laravel\Scout\Builder $query) => $query->where('path', $data->path))
            ->get();
    }

    public function operationDetails(PetStoreOperationDetailsDto $data): ?ApiDoc
    {
        return ApiDoc::query()
            ->where('type', 'operation')
            ->when($data->operationId !== '', fn (Builder $query) => $query->where('operation_id', $data->operationId))
            ->when($data->method !== '', fn (Builder $query) => $query->where('method', $data->method))
            ->when($data->path !== '', fn (Builder $query) => $query->where('path', $data->path))
            ->first();
    }

    public function definitionDetails(PetStoreDefinitionDetailsDto $data): ?ApiDoc
    {
        return ApiDoc::query()
            ->where('doc_key', "definition:$data->name")
            ->first();
    }

    public function tagDetails(PetStoreTagDetailsDto $data): Collection
    {
        $operations = ApiDoc::query()
            ->where('type', 'operation')
            ->whereJsonContains('meta->tags', $data->tag)
            ->orderBy('path')
            ->get();

        return $operations;
    }
}
