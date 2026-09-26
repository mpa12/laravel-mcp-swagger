<?php

namespace App\Console\Commands;

use App\Models\ApiDoc;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('swagger:import')]
#[Description('Import Swagger 2.0 documentation from app.swagger_url into api_docs')]
final class SwaggerImport extends Command
{
    private const HTTP_METHODS = ['get', 'post', 'put', 'delete', 'patch'];

    private array $spec;

    public function handle(): int
    {
        $url = config('app.swagger_url');

        if (empty($url)) {
            $this->components->error('SWAGGER_URL is not configured (config app.swagger_url is empty).');

            return self::FAILURE;
        }

        $this->components->info("Fetching {$url}");
        $json = @file_get_contents($url);

        if ($json === false) {
            $this->components->error('Failed to fetch the Swagger document.');

            return self::FAILURE;
        }

        $spec = json_decode($json, true);

        if (! is_array($spec)) {
            $this->components->error('Failed to decode the Swagger document as JSON.');

            return self::FAILURE;
        }

        if (($spec['swagger'] ?? null) !== '2.0') {
            $this->components->error('Only Swagger 2.0 documents are supported.');

            return self::FAILURE;
        }

        $this->spec = $this->resolveRefs($spec, $spec);

        ApiDoc::query()->delete();

        $this->importOperations();
        $this->importDefinitions();
        $this->importTags();

        return self::SUCCESS;
    }

    private function resolveRefs(mixed $node, array $root, int $depth = 0, array $references = []): mixed
    {
        if (! is_array($node) || $depth >= 30) {
            return $node;
        }

        $reference = $node['$ref'] ?? null;

        if (is_string($reference) && str_starts_with($reference, '#/')) {
            $pointer = rawurldecode(substr($reference, 1));
            $referenced = $root;

            foreach (explode('/', substr($pointer, 1)) as $segment) {
                if (! is_array($referenced) || ! array_key_exists($segment, $referenced)) {
                    $referenced = null;
                    break;
                }

                $referenced = $referenced[$segment];
            }

            if (is_array($referenced) && ! in_array($pointer, $references, true)) {
                $resolved = $this->resolveRefs($referenced, $root, $depth + 1, [...$references, $pointer]);
                unset($node['$ref']);

                foreach ($node as $key => $value) {
                    $node[$key] = $this->resolveRefs($value, $root, $depth + 1, $references);
                }

                return array_replace($resolved, $node);
            }
        }

        foreach ($node as $key => $value) {
            $node[$key] = $this->resolveRefs($value, $root, $depth + 1, $references);
        }

        return $node;
    }

    private function importOperations(): void
    {
        $count = 0;

        foreach ($this->spec['paths'] ?? [] as $path => $pathItem) {
            if (! is_array($pathItem)) {
                continue;
            }

            foreach (self::HTTP_METHODS as $method) {
                if (! is_array($pathItem[$method] ?? null)) {
                    continue;
                }

                $operation = $pathItem[$method];
                $operationId = $operation['operationId'] ?? null;
                $summary = $operation['summary'] ?? '';

                ApiDoc::query()->updateOrCreate([
                    'doc_key' => when(
                        filled($operationId),
                        'operation:'.$operationId,
                        'operation:'.strtoupper($method).':'.$path,
                    ),
                ], [
                    'type' => 'operation',
                    'operation_id' => $operationId,
                    'method' => strtoupper($method),
                    'path' => $path,
                    'title' => strtoupper($method)." $path".(filled($summary) ? " — $summary" : ''),
                    'content' => json_encode($operation, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                    'meta' => ['tags' => $operation['tags'] ?? []],
                ]);
                $count++;
            }
        }

        $this->components->info("Operations imported: {$count}");
    }

    private function importDefinitions(): void
    {
        $count = 0;

        foreach ($this->spec['definitions'] ?? [] as $name => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            ApiDoc::query()->updateOrCreate([
                'doc_key' => "definition:$name",
            ], [
                'type' => 'definition',
                'title' => $name,
                'content' => json_encode((object) $definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            ]);
            $count++;
        }

        $this->components->info("Definitions imported: {$count}");
    }

    private function importTags(): void
    {
        $count = 0;

        foreach ($this->spec['tags'] ?? [] as $tag) {
            if (! is_array($tag) || empty($tag['name'])) {
                continue;
            }

            ApiDoc::query()->updateOrCreate([
                'doc_key' => "tag:{$tag['name']}",
            ], [
                'type' => 'tag',
                'title' => $tag['name'],
                'content' => $tag['description'] ?? '',
                'meta' => ['tags' => []],
            ]);
            $count++;
        }

        $this->components->info("Tags imported: {$count}");
    }
}
