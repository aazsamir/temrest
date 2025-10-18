<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\OpenApi\Schema;

class OpenApi
{
    /**
     * @param Server[] $servers
     * @param Path[] $paths
     * @param Security[] $security
     * @param Tag[] $tags
     */
    public function __construct(
        public Info $info,
        public string $openapi = '3.0.0',
        public ?string $jsonSchemaDialect = null,
        public array $servers = [],
        public array $paths = [],
        // public array $webhooks = [],
        // public ?Components $components = null,
        public array $security = [],
        public array $tags = [],
        // public ?ExternalDocumentation $externalDocs = null,
    ) {}

    public function toArray(): array
    {
        $paths = [];
        $components = [];

        foreach ($this->paths as $path) {
            if (!isset($paths[$path->path])) {
                $paths[$path->path] = [];
            }

            $paths[$path->path][\strtolower($path->method)] = $path->toArray();
        }

        foreach ($this->namedSchemas() as $schema) {
            if (!isset($components['schemas'])) {
                $components['schemas'] = [];
            }

            $components['schemas'][$schema->name] = $schema->toArray(false);
        }

        $arr = [
            'openapi' => $this->openapi,
            'servers' => $this->servers,
            'info' => $this->info->toArray(),
            'paths' => $paths,
        ];

        if ($components) {
            $arr['components'] = $components;
        }

        return $arr;
    }

    /**
     * @return Schema[]
     */
    public function schemas(): array
    {
        $schemas = [];

        foreach ($this->paths as $path) {
            $schemas = array_merge($schemas, $path->schemas());
        }

        return $schemas;
    }

    public function namedSchemas(): array
    {
        $namedSchemas = [];
        
        foreach ($this->schemas() as $schema) {
            $this->saveSchema($schema, $namedSchemas);
        }

        return $namedSchemas;
    }

    private function saveSchema(Schema $schema, array &$namedSchemas): void
    {
        if ($schema->name === null) {
            return;
        }

        if (isset($namedSchemas[$schema->name])) {
            return;
        }

        $namedSchemas[$schema->name] = $schema;

        foreach ($schema->schemas() as $childSchema) {
            $this->saveSchema($childSchema, $namedSchemas);
        }
    }
}
