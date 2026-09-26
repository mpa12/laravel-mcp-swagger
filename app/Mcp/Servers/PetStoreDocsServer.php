<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\PetStoreDefinitionsDetailsTool;
use App\Mcp\Tools\PetStoreOperationDetailsTool;
use App\Mcp\Tools\PetStoreSearchTool;
use App\Mcp\Tools\PetStoreTagDetailsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('petstore-docs')]
#[Version('1.0.0')]
#[Instructions('Документация PetStore API (Swagger 2.0): поиск по операциям, схемам и тегам, детали операций и схем.')]
final class PetStoreDocsServer extends Server
{
    protected array $tools = [
        PetStoreSearchTool::class,
        PetStoreOperationDetailsTool::class,
        PetStoreDefinitionsDetailsTool::class,
        PetStoreTagDetailsTool::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
