<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\OpenApi\Metadata;

class ParametersMetadata
{
    /**
     * @param ParameterMetadata[] $parameters
     */
    public function __construct(
        public array $parameters = [],
    ) {}
}