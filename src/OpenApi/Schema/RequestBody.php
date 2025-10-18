<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\OpenApi\Schema;

class RequestBody
{
    public function __construct(
        public ?Schema $schema = null,
        public ?string $description = null,
        public bool $required = false,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'description' => $this->description,
            'required' => $this->required,
            'content' => $this->schema
                ? [
                    'application/json' => [
                        'schema' => $this->schema->toArray(true),
                    ],
                ] : null,
        ]);
    }
}
