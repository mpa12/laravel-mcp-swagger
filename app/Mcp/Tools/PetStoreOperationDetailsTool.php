<?php

namespace App\Mcp\Tools;

use App\Dto\PetStoreOperationDetailsDto;
use App\Services\ApiDocService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Детали операции PetStore API по operationId или method+path: возвращает полное описание операции.')]
final class PetStoreOperationDetailsTool extends Tool
{
    public function handle(Request $request, ApiDocService $service): Response
    {
        $validated = $request->validate([
            'operationId' => 'required_without_all:method,path|nullable|string',
            'method' => 'required_without:operationId|nullable|string|in:GET,POST,PUT,DELETE,PATCH',
            'path' => 'required_without:operationId|nullable|string',
        ], [
            'operationId.required_without_all' => 'Укажите operationId или пару method + path.',
            'method.required_without' => 'Укажите operationId или пару method + path.',
            'path.required_without' => 'Укажите operationId или пару method + path.',
            'method.in' => 'Параметр method должен быть одним из: GET, POST, PUT, DELETE, PATCH.',
        ]);

        $operation = $service->operationDetails(PetStoreOperationDetailsDto::fromArray($validated));

        if (blank($operation)) {
            return Response::error('Операция не найдена.');
        }

        return Response::text($operation->content);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'operationId' => $schema->string()
                ->description('operationId операции (приоритетный способ идентификации).'),
            'method' => $schema->string()
                ->enum(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'])
                ->description('HTTP-метод (используется вместе с path, если operationId не указан).'),
            'path' => $schema->string()
                ->description('Путь операции, например /contracts/{id}.'),
        ];
    }
}
