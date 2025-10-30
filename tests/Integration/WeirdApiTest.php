<?php

declare(strict_types=1);

namespace Tests\Integration;

use Aazsamir\Temrest\Api\Api;
use Aazsamir\Temrest\Api\ApiConfig;
use Aazsamir\Temrest\Api\Endpoint;
use Tempest\Http\Method;
use Tests\Fixtures\Pet\PetListRequest;
use Tests\Fixtures\Weird\AResponse;
use Tests\Fixtures\Weird\BResponse;

class WeirdApiTest extends IntegrationTestCase
{
    private function config(): ApiConfig
    {
        return new ApiConfig(
            name: 'Test API',
            endpoints: [
                new Endpoint(
                    route: new Api(
                        method: Method::GET,
                        uri: '/api/weird/union-response',
                    ),
                    requestClass: PetListRequest::class,
                    responseClass: AResponse::class . '|' . BResponse::class,
                ),
            ],
        );
    }

    public function testUnionResponse(): void
    {
        $schema = $this->schemaGenerator($this->config())->generate();
        $schema = $schema->toArray();
        
        $this->assertCount(2, $schema['paths']['/api/weird/union-response']['get']['responses']['200']['content']['application/json']['schema']['oneOf']);
    }
}