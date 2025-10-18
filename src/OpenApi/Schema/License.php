<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\OpenApi\Schema;

use Psr\Http\Message\UriInterface;

class License
{
    public function __construct(
        public string $name,
        public ?string $identifier = null,
        public string|UriInterface|null $url = null,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'identifier' => $this->identifier,
            'url' => is_string($this->url) ? $this->url : $this->url?->__toString() ?? null,
        ];
    }
}
