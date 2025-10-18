<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\OpenApi\Metadata;

class MethodMetadata
{
    /**
     * @param ParameterMetadata[] $parameters
     */
    public function __construct(
        public string $name,
        public array $parameters = [],
        public ?ArrayMetadata $returnType = null,
    ) {}

    public function addParameter(ParameterMetadata $parameter): void
    {
        $this->parameters[$parameter->name] = $parameter;
    }
}
