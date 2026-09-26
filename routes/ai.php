<?php

use App\Mcp\Servers\PetStoreDocsServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/petstore-docs', PetStoreDocsServer::class);
