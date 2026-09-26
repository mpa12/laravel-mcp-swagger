<?php

namespace App\Mcp\Tools;

use App\Dto\PetStoreDefinitionDetailsDto;
use App\Services\ApiDocService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('JSON-схема (definitions) PetStore API по имени.')]
final class PetStoreDefinitionsDetailsTool extends Tool
{
    public function handle(Request $request, ApiDocService $service): Response
    {
        $validated = $request->validate([
            'name' => 'required|string',
        ], [
            'name.required' => 'Параметр name не должен быть пустым.',
            'name.string' => 'Параметр name должен быть строкой.',
        ]);

        $definition = $service->definitionDetails(PetStoreDefinitionDetailsDto::fromArray($validated));

        if (blank($definition)) {
            return Response::error("Схема «{$validated['name']}» не найдена.");
        }

        return Response::text($definition->content);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('Имя схемы из definitions, например Pet.')
                ->required(),
        ];
    }
}
