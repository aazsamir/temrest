<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\Api;

class ApiConfig
{
    /**
     * @param Endpoint[] $endpoints
     */
    public function __construct(
        public string $name,
        public string $version = '1.0',
        public ?string $description = null,
        // internal
        public array $endpoints = [],
    ) {}

    public function addEndpoint(Endpoint $endpoint): void
    {
        $this->endpoints[] = $endpoint;
    }
}
