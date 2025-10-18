<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\Api;

use Tempest\Discovery\Discovery;
use Tempest\Discovery\DiscoveryLocation;
use Tempest\Discovery\IsDiscovery;
use Tempest\Reflection\ClassReflector;
use Tempest\Router\Route;
use Tempest\Router\RouteConfig;
use Tempest\Router\Routing\Construction\DiscoveredRoute;

class EndpointDiscovery implements Discovery
{
    use IsDiscovery;

    public function __construct(
        private ApiConfig $apiConfig,
        private RouteConfig $routeConfig,
    ) {}

    public function discover(DiscoveryLocation $location, ClassReflector $class): void
    {
        foreach ($class->getPublicMethods() as $method) {
            $routeAttributes = $method->getAttributes(Route::class);
            $apiInfoAttribute = $method->getAttribute(ApiInfo::class);

            foreach ($routeAttributes as $routeAttribute) {
                if ($routeAttribute instanceof Api && $apiInfoAttribute === null) {
                    $apiInfoAttribute = $routeAttribute->apiInfo;
                }

                $this->discoveryItems->add($location, [$method, $routeAttribute, $apiInfoAttribute]);
            }
        }
    }

    public function apply(): void
    {
        foreach ($this->discoveryItems as [$method, $routeAttribute, $apiInfoAttribute]) {
            /** @var \Tempest\Reflection\MethodReflector $method */
            /** @var Route $routeAttribute */
            /** @var ApiInfo|null $apiInfoAttribute */

            $discoveredRoute = DiscoveredRoute::fromRoute($routeAttribute, $method);
            $endpoint = Endpoint::fromRoute($routeAttribute, $discoveredRoute, $method, $apiInfoAttribute);

            $this->apiConfig->addEndpoint($endpoint);
        }
    }
}
