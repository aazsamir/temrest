<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\Api;

use Attribute;
use Tempest\Http\Method;
use Tempest\Router\Route;

#[Attribute(Attribute::TARGET_METHOD)]
class Api implements Route
{
    public function __construct(
        public Method $method,
        public string $uri,
        public array $middleware = [],
        public array $without = [],
        public ?ApiInfo $apiInfo = null,
    ) {}
}
