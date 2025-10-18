<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\OpenApi\Schema;

class Path
{
    /**
     * @param Response[] $responses
     * @param Parameter[] $parameters
     */
    public function __construct(
        public string $path,
        public string $method,
        public ?string $description = null,
        public ?string $summary = null,
        public ?string $operationId = null,
        public array $responses = [],
        public array $parameters = [],
        public ?RequestBody $requestBody = null,
    ) {}

    public function toArray(): array
    {
        $responses = [];

        foreach ($this->responses as $response) {
            $responses[$response->statusCode] = $response->toArray();
        }

        return array_filter([
            'description' => $this->description,
            'summary' => $this->summary,
            'operationId' => $this->operationId,
            'responses' => $responses,
            'parameters' => array_map(fn(Parameter $parameter) => $parameter->toArray(), $this->parameters),
            'requestBody' => $this->requestBody?->toArray(),
        ]);
    }

    public function schemas(): array
    {
        $schemas = [];

        foreach ($this->responses as $response) {
            if ($response->schema) {
                $schemas[] = $response->schema;
            }
        }

        if ($this->requestBody?->schema) {
            $schemas[] = $this->requestBody->schema;
        }

        return $schemas;
    }
}
