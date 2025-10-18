<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\OpenApi\Schema;

class Parameter
{
    public function __construct(
        public string $name,
        public string $in,
        public ?string $description = null,
        public ?bool $required = null,
        public ?bool $deprecated = null,
        public ?bool $allowEmptyValue = null,
        public ?Schema $schema = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'in' => $this->in,
            'description' => $this->description,
            'required' => $this->required,
            'deprecated' => $this->deprecated,
            'allowEmptyValue' => $this->allowEmptyValue,
            'schema' => $this->schema?->toArray(),
        ]);
    }
}
