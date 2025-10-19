<?php

declare(strict_types=1);

namespace Tests\Integration;

use Aazsamir\Temrest\Api\Api;
use Aazsamir\Temrest\Api\ApiConfig;
use Aazsamir\Temrest\Api\Endpoint;
use Tempest\Http\Method;
use Tests\Fixtures\Pet\PetListRequest;
use Tests\Fixtures\Pet\PetListResponse;
use Tests\Fixtures\Pet\PetStoreRequest;

class PetApiTest extends IntegrationTestCase
{
    private function config(): ApiConfig
    {
        return new ApiConfig(
            name: 'Test API',
            endpoints: [
                new Endpoint(
                    route: new Api(
                        method: Method::GET,
                        uri: '/api/pets',
                    ),
                    requestClass: PetListRequest::class,
                    responseClass: PetListResponse::class,
                ),
                new Endpoint(
                    route: new Api(
                        method: Method::GET,
                        uri: '/api/pets/{id}',
                    ),
                    pathParameters: [
                        'id',
                    ],
                ),
                new Endpoint(
                    route: new Api(
                        method: Method::POST,
                        uri: '/api/pets',
                    ),
                    requestClass: PetStoreRequest::class,
                ),
            ],
        );
    }

    public function testOutput(): void
    {
        $schema = $this->schemaGenerator($this->config())->generate();

        $this->assertEquals(
            [
                'openapi' => '3.0.0',
                'servers' => [],
                'info' => [
                    'title' => 'Test API',
                    'version' => '1.0',
                ],
                'paths' => [
                    '/api/pets' => [
                        'get' => [
                            'responses' => [
                                '200' => [
                                    'description' => 'Successful Response',
                                    'content' => [
                                        'application/json' => [
                                            'schema' => [
                                                'type' => 'array',
                                                'items' => [
                                                    '$ref' => '#/components/schemas/Pet',
                                                ],
                                                'nullable' => false,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'parameters' => [
                                [
                                    'name' => 'page',
                                    'in' => 'query',
                                    'schema' => [
                                        'type' => 'integer',
                                        'nullable' => true,
                                    ],
                                ],
                            ],
                        ],
                        'post' => [
                            'responses' => [
                                '200' => [
                                    'description' => 'Successful Response',
                                ],
                            ],
                            'requestBody' => [
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            '$ref' => '#/components/schemas/PetStoreRequest',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '/api/pets/{id}' => [
                        'get' => [
                            'responses' => [
                                '200' => [
                                    'description' => 'Successful Response',
                                ],
                            ],
                            'parameters' => [
                                [
                                    'name' => 'id',
                                    'in' => 'path',
                                    'required' => true,
                                    'schema' => [
                                        'type' => 'string',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'components' => [
                    'schemas' => [
                        'Pet' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => [
                                    'type' => 'integer',
                                    'nullable' => false,
                                ],
                                'name' => [
                                    'type' => 'string',
                                    'nullable' => false,
                                ],
                                'type' => [
                                    '$ref' => '#/components/schemas/PetType',
                                ],
                                'cuteness' => [
                                    '$ref' => '#/components/schemas/PetCuteness',
                                ],
                                'tags' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'string',
                                        'nullable' => false,
                                    ],
                                    'nullable' => false,
                                ],
                            ],
                            'nullable' => false,
                        ],
                        'PetType' => [
                            'type' => 'string',
                            'enum' => [
                                'dog',
                                'cat',
                            ],
                            'nullable' => false,
                        ],
                        'PetCuteness' => [
                            'type' => 'string',
                            'enum' => [
                                'Cute',
                                'VeryCute',
                            ],
                            'nullable' => false,
                        ],
                        'PetStoreRequest' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => [
                                    'type' => 'string',
                                    'nullable' => false,
                                ],
                                'type' => [
                                    '$ref' => '#/components/schemas/PetType',
                                ],
                                'cuteness' => [
                                    '$ref' => '#/components/schemas/PetCuteness',
                                ],
                                'tags' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'string',
                                        'nullable' => false,
                                    ],
                                    'nullable' => false,
                                ],
                            ],
                            'nullable' => false,
                        ],
                    ],
                ],
            ],
            $schema->toArray(),
        );
    }
}
