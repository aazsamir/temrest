<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\OpenApi\Metadata;

class PropertyMetadata
{
    public function __construct(
        public string $name,
        public ArrayMetadata $type,
    ) {}
}
