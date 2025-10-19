<?php

declare(strict_types=1);

namespace Tests\Unit\OpenApi;

use Aazsamir\Temrest\Api\ApiConfig;
use Aazsamir\Temrest\OpenApi\Metadata\ClassMetadata;
use Aazsamir\Temrest\OpenApi\Metadata\MetadataExtractor;
use Aazsamir\Temrest\OpenApi\Schema\Schema;
use Aazsamir\Temrest\OpenApi\SchemaGenerator;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\TypeReflector;
use Tests\Fixtures\Arrays;
use Tests\Fixtures\DefaultValue;
use Tests\Fixtures\PlainObject;
use Tests\Fixtures\PureEnum;
use Tests\Fixtures\StringEnum;
use Tests\Fixtures\UnionType;
use Tests\TestCase;

class SchemaGeneratorTest extends TestCase
{
    private SchemaGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new SchemaGenerator(
            new ApiConfig(name: 'Test API'),
            new MetadataExtractor(),
        );
    }

    public function testPlainObject(): void
    {
        $schema = $this->schemaForType(PlainObject::class);
        $expected = new Schema(
            name: 'PlainObject',
            type: 'object',
            properties: [
                'int' => new Schema(
                    type: 'integer',
                    nullable: false,
                ),
                'string' => new Schema(
                    type: 'string',
                    nullable: false,
                ),
                'bool' => new Schema(
                    type: 'boolean',
                    nullable: false,
                ),
                'float' => new Schema(
                    type: 'number',
                    format: 'float',
                    nullable: false,
                ),
                'nullableInt' => new Schema(
                    type: 'integer',
                    nullable: true,
                ),
            ],
            nullable: false,
        );

        $this->assertSchemas($expected, $schema);
    }

    public function testArrays(): void
    {
        $schema = $this->schemaForType(Arrays::class);
        $expected = new Schema(
            name: 'Arrays',
            type: 'object',
            properties: [
                'array' => new Schema(
                    type: 'array',
                    items: new Schema(
                        type: 'integer',
                        nullable: false,
                    ),
                    nullable: false,
                ),
            ],
            nullable: false,
        );

        $this->assertSchemas($expected, $schema);
    }

    public function testUnion(): void
    {
        $schema = $this->schemaForType(UnionType::class);
        $expected = new Schema(
            name: 'UnionType',
            type: 'object',
            properties: [
                'intOrString' => new Schema(
                    oneOf: [
                        new Schema(
                            type: 'string',
                            nullable: false,
                        ),
                        new Schema(
                            type: 'integer',
                            nullable: false,
                        ),
                    ],
                    nullable: false,
                ),
            ],
            nullable: false,
        );

        $this->assertSchemas($expected, $schema);
    }

    public function testPureEnum(): void
    {
        $schema = $this->schemaForType(PureEnum::class);
        $expected = new Schema(
            name: 'PureEnum',
            type: 'string',
            enum: ['FOO', 'BAR', 'BAZ'],
            nullable: false,
        );

        $this->assertSchemas($expected, $schema);
    }

    public function testStringEnum(): void
    {
        $schema = $this->schemaForType(StringEnum::class);
        $expected = new Schema(
            name: 'StringEnum',
            type: 'string',
            enum: ['foo', 'bar', 'baz'],
            nullable: false,
        );

        $this->assertSchemas($expected, $schema);
    }

    public function testDefaultValue(): void
    {
        $schema = $this->schemaForType(DefaultValue::class);
        $expected = new Schema(
            name: 'DefaultValue',
            type: 'object',
            properties: [
                'intWithDefault' => new Schema(
                    type: 'integer',
                    nullable: true, // TODO: it should stay as non-nullable, but not required
                ),
                'nullableIntWithDefault' => new Schema(
                    type: 'integer',
                    nullable: true,
                ),
            ],
            nullable: false,
        );

        $this->assertSchemas($expected, $schema);
    }

    private function schemaForType(string $type): Schema
    {
        return $this->generator->typeToSchema(new TypeReflector($type));
    }

    private function assertSchemas(Schema $expected, Schema $actual): void
    {
        $this->assertEquals($expected->toArray(false), $actual->toArray(false));
    }
}