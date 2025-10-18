<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\OpenApi\Schema;

class ServerVariable
{
    public function __construct(
        public string $default,
        public ?string $enum = null,
        public ?string $description = null,
    ) {}
}
