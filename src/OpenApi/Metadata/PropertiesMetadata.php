<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\OpenApi\Metadata;

class PropertiesMetadata
{
    /**
     * @param PropertyMetadata[] $items
     */
    public function __construct(
        public array $items = [],
    ) {}

    public function addProperty(PropertyMetadata $propertyMetadata): void
    {
        $this->items[$propertyMetadata->name] = $propertyMetadata;
    }

    public function getProperty(string $name): ?PropertyMetadata
    {
        return $this->items[$name] ?? null;
    }
}
