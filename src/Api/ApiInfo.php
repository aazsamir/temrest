<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\Api;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class ApiInfo
{
    public function __construct(
        public ?string $description = null,
        public ?string $operationId = null,
        public ?string $summary = null,
    ) {}
}
