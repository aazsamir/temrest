<?php

declare(strict_types=1);

namespace Tests\Integration;

use Aazsamir\Temrest\Api\ApiConfig;
use Aazsamir\Temrest\OpenApi\Metadata\MetadataExtractor;
use Aazsamir\Temrest\OpenApi\SchemaGenerator;
use Tests\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    protected function schemaGenerator(ApiConfig $config): SchemaGenerator
    {
        return new SchemaGenerator(
            $config,
            new MetadataExtractor(),
        );
    }
}