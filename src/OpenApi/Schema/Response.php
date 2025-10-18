<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\OpenApi\Schema;

class Response
{
    public function __construct(
        public int $statusCode,
        public ?string $description = null,
        public ?Schema $schema = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'description' => $this->description,
            'content' => $this->schema ? [
                'application/json' => [
                    'schema' => $this->schema->toArray(),
                ],
            ] : null,
        ]);
    }
}
