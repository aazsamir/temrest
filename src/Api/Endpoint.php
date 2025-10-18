<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\Api;

use Tempest\Http\Request;
use Tempest\Reflection\MethodReflector;
use Tempest\Router\Route;
use Tempest\Router\Routing\Construction\DiscoveredRoute;

class Endpoint
{
    public function __construct(
        public Route $route,
        public array $pathParameters = [],
        public ?string $requestClass = null,
        public ?string $responseClass = null,
    ) {}

    public static function fromRoute(
        Route $route,
        DiscoveredRoute $discoveredRoute,
        MethodReflector $method,
    ): self {
        $requestClass = null;

        foreach ($method->getParameters() as $parameter) {
            if ($parameter->getType()->matches(Request::class)) {
                $requestClass = $parameter->getType()->getName();

                break;
            }
        }

        try {
            // @TODO: https://github.com/tempestphp/tempest-framework/pull/1645/files
            $responseClass = $method->getReturnType()?->getName();
        } catch (\Throwable $e) {
            $responseClass = null;
        }

        return new self(
            route: $route,
            pathParameters: $discoveredRoute->parameters,
            requestClass: $requestClass,
            responseClass: $responseClass,
        );
    }
}
