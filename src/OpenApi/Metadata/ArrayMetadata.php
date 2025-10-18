<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\OpenApi\Metadata;

class ArrayMetadata
{
    public function __construct(
        public string $type,
        public ?string $key = null,
    ) {}

    public function docBlock(): string
    {
        $docType = $this->type;

        if ($this->key !== null) {
            $docType = "{$this->key}, {$docType}";
        }

        return "array<{$docType}>";
    }
}
