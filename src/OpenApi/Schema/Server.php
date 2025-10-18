<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\OpenApi\Schema;

use Psr\Http\Message\UriInterface;

class Server
{
    /** @param array<string, ServerVariable> */
    public function __construct(
        public string|UriInterface $url,
        public ?string $description = null,
        public array $variables = [],
    ) {}
}
