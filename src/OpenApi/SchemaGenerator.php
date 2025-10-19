<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\OpenApi;

use Aazsamir\Temrest\Api\ApiConfig;
use Aazsamir\Temrest\Api\ApiResponse;
use Aazsamir\Temrest\Api\Endpoint;
use Aazsamir\Temrest\OpenApi\Metadata\ArrayMetadata;
use Aazsamir\Temrest\OpenApi\Metadata\MetadataExtractor;
use Aazsamir\Temrest\OpenApi\Schema\Info;
use Aazsamir\Temrest\OpenApi\Schema\OpenApi;
use Aazsamir\Temrest\OpenApi\Schema\Parameter;
use Aazsamir\Temrest\OpenApi\Schema\Path;
use Aazsamir\Temrest\OpenApi\Schema\RequestBody;
use Aazsamir\Temrest\OpenApi\Schema\Response;
use Aazsamir\Temrest\OpenApi\Schema\Schema;
use DateTimeInterface;
use Tempest\Http\Method;
use Tempest\Http\Request;
use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;
use Tempest\Reflection\PropertyReflector;
use Tempest\Reflection\TypeReflector;
use Tempest\Validation\SkipValidation;

class SchemaGenerator
{
    private const METHODS_WITH_BODY = [Method::POST, Method::PUT, Method::PATCH];
    private const BUILT_IN_TYPE_MAPPINGS = [
        'int' => 'integer',
        'bool' => 'boolean',
    ];

    /**
     * @var array<string, Schema>
     */
    private array $schemas = [];

    public function __construct(
        private readonly ApiConfig $config,
        private readonly MetadataExtractor $metadataExtractor,
    ) {}

    public function generate(): OpenApi
    {
        $openapi = $this->createOpenApi();
        $this->addPaths($openapi);

        return $openapi;
    }

    private function createOpenApi(): OpenApi
    {
        return new OpenApi(
            info: new Info(
                title: $this->config->name,
                description: $this->config->description,
            ),
        );
    }

    private function addPaths(OpenApi $openapi): void
    {
        $paths = array_map(
            fn (Endpoint $endpoint) => $this->createPathFromEndpoint($endpoint),
            $this->config->endpoints,
        );

        $openapi->paths = $paths;
    }

    private function createPathFromEndpoint(Endpoint $endpoint): Path
    {
        $path = new Path(
            path: $endpoint->route->uri,
            method: $endpoint->route->method->value,
        );

        $path->parameters = $this->endpointToParameters($endpoint);
        $path->requestBody = $this->endpointToRequestBody($endpoint);
        $path->responses = $this->endpointToResponses($endpoint);

        $this->setPathMetadata($path, $endpoint);

        return $path;
    }

    private function setPathMetadata(Path $path, Endpoint $endpoint): void
    {
        if (!$endpoint->apiInfo) {
            return;
        }

        $path->description = $endpoint->apiInfo->description;
        $path->summary = $endpoint->apiInfo->summary;
        $path->operationId = $endpoint->apiInfo->operationId;
    }

    private function endpointToRequestBody(Endpoint $endpoint): ?RequestBody
    {
        if ($endpoint->requestClass === null || !$this->methodHasBody($endpoint->route->method)) {
            return null;
        }

        return new RequestBody(
            schema: $this->typeToSchema(new TypeReflector($endpoint->requestClass)),
        );
    }

    private function methodHasBody(Method $method): bool
    {
        return in_array($method, self::METHODS_WITH_BODY, true);
    }

    private function endpointToParameters(Endpoint $endpoint): array
    {
        $parameters = $this->createPathParameters($endpoint->pathParameters);

        if ($this->methodHasBody($endpoint->route->method)) {
            return $parameters;
        }

        if ($endpoint->requestClass !== null) {
            $parameters = array_merge($parameters, $this->createQueryParameters($endpoint));
        }

        return $parameters;
    }

    /**
     * @param string[] $pathParams
     */
    private function createPathParameters(array $pathParams): array
    {
        return array_map(
            fn (string $param) => new Parameter(
                name: $param,
                in: 'path',
                required: true,
                schema: new Schema(type: 'string'),
            ),
            $pathParams,
        );
    }

    private function createQueryParameters(Endpoint $endpoint): array
    {
        $schema = $this->typeToSchema(new TypeReflector($endpoint->requestClass));
        $parameters = [];

        foreach ($schema->properties ?? [] as $propertyName => $property) {
            $parameters[] = new Parameter(
                name: $propertyName,
                in: 'query',
                required: !$property->nullable,
                schema: $property,
            );
        }

        return $parameters;
    }

    private function endpointToResponses(Endpoint $endpoint): array
    {
        return [$this->createSuccessResponse($endpoint)];
    }

    private function createSuccessResponse(Endpoint $endpoint): Response
    {
        $response = new Response(statusCode: 200, description: 'Successful Response');

        if ($endpoint->responseClass === null) {
            return $response;
        }

        $classReflector = new ClassReflector($endpoint->responseClass);

        if ($classReflector->is(ApiResponse::class)) {
            $methodReflector = $classReflector->getMethod('toResponse');
            $response->schema = $this->methodReturnToSchema($methodReflector);
        }

        return $response;
    }

    private function methodReturnToSchema(MethodReflector $methodReflector): Schema
    {
        $classMetadata = $this->metadataExtractor->getClassMetadata(
            $methodReflector->getDeclaringClass(),
        );

        return $this->typeToSchema(
            $methodReflector->getReturnType(),
            $classMetadata->methods->getMethodReturnType($methodReflector->getName()),
        );
    }

    public function typeToSchema(TypeReflector $type, ?ArrayMetadata $arrayMeta = null): Schema
    {
        $typeKey = $this->generateTypeKey($type, $arrayMeta);

        if (isset($this->schemas[$typeKey])) {
            return $this->schemas[$typeKey];
        }

        $schema = new Schema();
        $this->schemas[$typeKey] = $schema;
        $schema->nullable = $type->isNullable();

        $this->populateSchema($type, $schema, $arrayMeta);

        return $schema;
    }

    private function generateTypeKey(TypeReflector $type, ?ArrayMetadata $arrayMeta): string
    {
        return md5(json_encode([
            'type' => $type->getName(),
            'nullable' => $type->isNullable(),
            'arrayMeta' => $arrayMeta?->docBlock(),
        ]));
    }

    private function populateSchema(TypeReflector $type, Schema $schema, ?ArrayMetadata $arrayMeta): void
    {
        match (true) {
            $type->isIterable() => $this->handleIterableType($type, $schema, $arrayMeta),
            $type->getName() === 'float' => $this->handleFloatType($schema),
            $type->isBuiltIn() => $schema->type = $this->mapBuiltInType($type),
            $type->isEnum() => $this->handleEnumType($type, $schema),
            $type->matches(DateTimeInterface::class) => $this->handleDateTimeType($schema),
            $type->isClass() => $this->handleClassType($type, $schema),
            str_contains($type->getName(), '|') => $this->handleUnionType($type, $schema),
            default => throw new TypeNotSupported($type),
        };
    }

    private function handleFloatType(Schema $schema): void
    {
        $schema->type = 'number';
        $schema->format = 'float';
    }

    private function mapBuiltInType(TypeReflector $type): string
    {
        $typeName = $type->getName();

        return self::BUILT_IN_TYPE_MAPPINGS[$typeName] ?? $typeName;
    }

    private function handleDateTimeType(Schema $schema): void
    {
        $schema->type = 'string';
        $schema->format = 'date-time';
    }

    private function handleIterableType(TypeReflector $type, Schema $schema, ?ArrayMetadata $arrayMeta): void
    {
        $schema->type = 'array';

        if ($arrayMeta !== null) {
            $schema->items = $this->typeToSchema(new TypeReflector($arrayMeta->type));
        }
    }

    private function handleEnumType(TypeReflector $type, Schema $schema): void
    {
        $schema->type = 'string';
        $schema->name = $type->getShortName();

        $cases = $type->isBackedEnum()
            ? array_map(fn ($case) => $case->value, $type->getName()::cases())
            : array_map(fn ($case) => $case->name, $type->getName()::cases());

        $schema->enum = $cases;
    }

    private function handleClassType(TypeReflector $type, Schema $schema): void
    {
        $schema->name = $type->getShortName();
        $schema->type = 'object';
        $schema->properties = $this->extractClassProperties($type);
    }

    private function extractClassProperties(TypeReflector $type): array
    {
        $properties = [];
        $classMeta = $this->metadataExtractor->getClassMetadata($type->asClass());

        foreach ($type->asClass()->getProperties() as $property) {
            if ($this->shouldSkipProperty($property, $type)) {
                continue;
            }

            $arrayMeta = $classMeta->properties->getProperty($property->getName());
            $propertySchema = $this->typeToSchema($property->getType(), $arrayMeta?->type);
            $propertySchema->nullable = $propertySchema->nullable || $property->hasDefaultValue();
            $properties[$property->getName()] = $propertySchema;
        }

        return $properties;
    }

    private function handleUnionType(TypeReflector $type, Schema $schema): void
    {
        $types = array_map(
            fn (string $unionType) => $this->typeToSchema(new TypeReflector($unionType)),
            explode('|', $type->getName()),
        );

        $schema->oneOf = $types;
    }

    private function shouldSkipProperty(PropertyReflector $propertyReflector, TypeReflector $parentType): bool
    {
        return $parentType->matches(Request::class) && $propertyReflector->hasAttribute(SkipValidation::class);
    }
}
