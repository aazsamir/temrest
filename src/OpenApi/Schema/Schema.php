<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\OpenApi\Schema;

class Schema
{
    /**
     * @param array<string, Schema>|null $properties
     * @param bool|null $additionalProperties
     * @param Schema[]|null $allOf
     * @param Schema[]|null $oneOf
     * @param Schema[]|null $anyOf
     * @param mixed $default
     * @param mixed $example
     * @param array<int, mixed>|null $enum
     */
    public function __construct(
        public ?string $name = null,
        public ?string $type = null,
        public ?string $format = null,
        public ?string $pattern = null,
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public ?float $minimum = null,
        public ?float $maximum = null,
        public ?bool $exclusiveMinimum = null,
        public ?bool $exclusiveMaximum = null,
        public ?int $minItems = null,
        public ?int $maxItems = null,
        public ?bool $uniqueItems = null,
        public ?int $minProperties = null,
        public ?int $maxProperties = null,
        public ?array $required = null,
        public ?Schema $items = null,
        public ?array $properties = null,
        public ?bool $additionalProperties = null,
        public mixed $default = null,
        public mixed $example = null,
        public ?array $enum = null,
        public ?bool $nullable = null,
        public ?bool $readOnly = null,
        public ?bool $writeOnly = null,
        public ?array $allOf = null,
        public ?array $oneOf = null,
        public ?array $anyOf = null,
    ) {}

    public function toArray(bool $asReference = true): array
    {
        if ($this->name && $asReference) {
            $data = [
                '$ref' => "#/components/schemas/{$this->name}",
            ];

            return $data;
        }

        $data = [
            'type' => $this->type,
            'format' => $this->format,
            'pattern' => $this->pattern,
            'minLength' => $this->minLength,
            'maxLength' => $this->maxLength,
            'minimum' => $this->minimum,
            'maximum' => $this->maximum,
            'exclusiveMinimum' => $this->exclusiveMinimum,
            'exclusiveMaximum' => $this->exclusiveMaximum,
            'minItems' => $this->minItems,
            'maxItems' => $this->maxItems,
            'uniqueItems' => $this->uniqueItems,
            'minProperties' => $this->minProperties,
            'maxProperties' => $this->maxProperties,
            'required' => $this->required,
            'items' => $this->items?->toArray(true),
            'properties' => $this->properties ? array_map(fn(Schema $schema) => $schema->toArray(true), $this->properties) : null,
            'additionalProperties' => $this->additionalProperties,
            'default' => $this->default,
            'example' => $this->example,
            'enum' => $this->enum,
            'nullable' => $this->nullable,
            'readOnly' => $this->readOnly,
            'writeOnly' => $this->writeOnly,
            'allOf' => $this->allOf ? array_map(fn(Schema $schema) => $schema->toArray(true), $this->allOf) : null,
            'oneOf' => $this->oneOf ? array_map(fn(Schema $schema) => $schema->toArray(true), $this->oneOf) : null,
            'anyOf' => $this->anyOf ? array_map(fn(Schema $schema) => $schema->toArray(true), $this->anyOf) : null,
        ];

        return array_filter($data, fn($value) => !is_null($value));
    }

    /**
     * @return Schema[]
     */
    public function schemas(): array
    {
        $schemas = [];

        if ($this->properties) {
            foreach ($this->properties as $property) {
                $schemas[] = $property;
            }
        }

        if ($this->items) {
            $schemas[] = $this->items;
        }

        if ($this->allOf) {
            foreach ($this->allOf as $schema) {
                $schemas[] = $schema;
            }
        }

        if ($this->oneOf) {
            foreach ($this->oneOf as $schema) {
                $schemas[] = $schema;
            }
        }

        if ($this->anyOf) {
            foreach ($this->anyOf as $schema) {
                $schemas[] = $schema;
            }
        }

        return $schemas;
    }
}
