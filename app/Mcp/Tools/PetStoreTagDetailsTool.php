<?php

namespace App\Mcp\Tools;

use App\Dto\PetStoreTagDetailsDto;
use App\Models\ApiDoc;
use App\Services\ApiDocService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Список операций PetStore API по тегу.')]
final class PetStoreTagDetailsTool extends Tool
{
    public function handle(Request $request, ApiDocService $service): ResponseFactory|Response
    {
        $validated = $request->validate([
            'tag' => 'required|string',
        ], [
            'tag.required' => 'Параметр tag не должен быть пустым.',
            'tag.string' => 'Параметр tag должен быть строкой.',
        ]);

        $content = $service->tagDetails(PetStoreTagDetailsDto::fromArray($validated));

        if ($content->isEmpty()) {
            return Response::error("Операции с тегом «{$validated['tag']}» не найдены.");
        }

        return Response::structured(
            $content->map(fn (ApiDoc $doc) => [
                'method' => $doc->method,
                'path' => $doc->path,
                'title' => $doc->title,
            ])
            ->toArray(),
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tag' => $schema->string()
                ->description('Имя тега, например pet.')
                ->required(),
        ];
    }
}
