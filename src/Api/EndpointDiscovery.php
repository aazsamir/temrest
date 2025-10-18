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

            foreach ($routeAttributes as $routeAttribute) {
                $this->discoveryItems->add($location, [$method, $routeAttribute]);
            }
        }
    }

    public function apply(): void
    {
        foreach ($this->discoveryItems as [$method, $routeAttribute]) {
            /** @var \Tempest\Reflection\MethodReflector $method */
            /** @var Route $routeAttribute */

            $discoveredRoute = DiscoveredRoute::fromRoute($routeAttribute, $method);
            $endpoint = Endpoint::fromRoute($routeAttribute, $discoveredRoute, $method);

            $this->apiConfig->addEndpoint($endpoint);
        }
    }
}
