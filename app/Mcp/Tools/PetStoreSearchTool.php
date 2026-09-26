<?php

namespace App\Mcp\Tools;

use App\Dto\PetStoreSearchDto;
use App\Models\ApiDoc;
use App\Services\ApiDocService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Поиск по документации PetStore (Swagger 2.0) с фильтрами по типу документа, HTTP-методу и пути. Возвращает operationId, метод, путь, краткое описание.')]
final class PetStoreSearchTool extends Tool
{
    private const TYPES = ['operation', 'definition', 'tag'];

    private const METHODS = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

    public function handle(Request $request, ApiDocService $service): Response|ResponseFactory
    {
        $validated = $request->validate([
            'query' => 'required|string',
            'type' => 'nullable|string|in:'.implode(',', self::TYPES),
            'method' => 'nullable|string|in:'.implode(',', self::METHODS),
            'path' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:50',
        ], [
            'query.required' => 'Параметр query не должен быть пустым.',
            'type.in' => 'Параметр type должен быть одним из: '.implode(', ', self::TYPES).'.',
            'method.in' => 'Параметр method должен быть одним из: '.implode(', ', self::METHODS).'.',
            'limit.integer' => 'Параметр limit должен быть числом.',
            'limit.min' => 'Параметр limit должен быть не меньше 1.',
            'limit.max' => 'Параметр limit не должен превышать 50.',
        ]);

        $results = $service->search(PetStoreSearchDto::fromArray($validated));

        if ($results->isEmpty()) {
            return Response::error("По запросу «{$validated['query']}» ничего не найдено.");
        }

        return Response::structured(
            $results
                ->map(fn (ApiDoc $doc): array => [
                    'operationId' => $doc->operation_id,
                    'method' => $doc->method,
                    'path' => $doc->path,
                    'title' => $doc->title,
                    'type' => $doc->type,
                    'description' => Str::limit($doc->content ?? '', 200),
                ])
                ->values()
                ->toArray(),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Поисковый запрос (полнотекстовый поиск по документации).')
                ->required(),
            'type' => $schema->string()
                ->enum(self::TYPES)
                ->description('Ограничить поиск типом документа.'),
            'method' => $schema->string()
                ->enum(self::METHODS)
                ->description('Фильтр по HTTP-методу операции (например GET).'),
            'path' => $schema->string()
                ->description('Фильтр по точному пути операции, например /pet/{petId}.'),
            'limit' => $schema->integer()
                ->default(10)
                ->max(50)
                ->description('Максимальное количество результатов (по умолчанию 10, максимум 50).'),
        ];
    }
}
