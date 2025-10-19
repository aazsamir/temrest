<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\Api;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class ApiInfo
{
    /**
     * @param ?string $requestClass Override the request class for this endpoint
     * @param ?string $responseClass Override the response class for this endpoint
     */
    public function __construct(
        public ?string $description = null,
        public ?string $operationId = null,
        public ?string $summary = null,
        public ?string $requestClass = null,
        public ?string $responseClass = null,
    ) {}
}
