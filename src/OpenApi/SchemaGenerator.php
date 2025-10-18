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
    private array $schemas = [];

    public function __construct(
        private ApiConfig $config,
        private MetadataExtractor $metadataExtractor,
    ) {}

    public function generate(): OpenApi
    {
        $openapi = $this->createOpenApi();
        $this->addPaths($openapi);

        return $openapi;
    }

    private function createOpenApi(): OpenApi
    {
        $openapi = new OpenApi(
            info: new Info(
                title: $this->config->name,
                description: $this->config->description,
            ),
        );

        return $openapi;
    }

    private function addPaths(OpenApi $openapi): void
    {
        $paths = [];

        foreach ($this->config->endpoints as $endpoint) {
            $path = new Path(
                path: $endpoint->route->uri,
                method: $endpoint->route->method->value,
            );
            $path->parameters = $this->endpointToParameters($endpoint);
            $path->requestBody = $this->endpointToRequestBody($endpoint);
            $path->responses = $this->endpointToResponses($endpoint);

            if ($endpoint->apiInfo) {
                $path->description = $endpoint->apiInfo->description;
                $path->summary = $endpoint->apiInfo->summary;
                $path->operationId = $endpoint->apiInfo->operationId;
            }


            $paths[] = $path;
        }

        $openapi->paths = $paths;
    }

    private function endpointToRequestBody(Endpoint $endpoint): ?RequestBody
    {
        if ($endpoint->requestClass === null) {
            return null;
        }

        if (
            $endpoint->route->method !== Method::POST &&
            $endpoint->route->method !== Method::PUT &&
            $endpoint->route->method !== Method::PATCH
        ) {
            return null;
        }

        $request = new RequestBody();
        $request->schema = $this->typeToSchema(
            new TypeReflector($endpoint->requestClass),
        );

        return $request;
    }

    /**
     * @return Parameter[]
     */
    private function endpointToParameters(Endpoint $endpoint): array
    {
        $parameters = [];

        foreach ($endpoint->pathParameters as $param) {
            $parameters[] = new Parameter(
                name: $param,
                in: 'path',
                required: true,
                schema: new Schema(
                    type: 'string',
                ),
            );
        }

        if ($endpoint->requestClass === null) {
            return $parameters;
        }

        $schema = $this->typeToSchema(
            new TypeReflector($endpoint->requestClass),
        );

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

    /**
     * @return Response[]
     */
    private function endpointToResponses(Endpoint $endpoint): array
    {
        return [
            $this->endpointToOkResponse($endpoint),
        ];
    }

    private function endpointToOkResponse(Endpoint $endpoint): Response
    {
        $response = new Response(
            statusCode: 200,
        );
        $response->description = 'Successful Response';

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
        $classMetadata = $this->metadataExtractor->getClassMetadata($methodReflector->getDeclaringClass());
        $returnTypeReflector = $methodReflector->getReturnType();

        return $this->typeToSchema(
            $returnTypeReflector,
            $classMetadata->methods->getMethodReturnType($methodReflector->getName())
        );
    }

    private function typeToSchema(TypeReflector $type, ?ArrayMetadata $arrayMeta = null): Schema
    {
        $typekey = md5(json_encode([
            'type' => $type->getName(),
            'nullable' => $type->isNullable(),
            'arrayMeta' => $arrayMeta?->docBlock(),
        ]));

        if (array_key_exists($typekey, $this->schemas)) {
            return $this->schemas[$typekey];
        }

        $schema = new Schema();
        $this->schemas[$typekey] = $schema;
        $schema->nullable = $type->isNullable();

        if ($type->isIterable()) {
            $this->handleIterableType($type, $schema, $arrayMeta);
        } elseif ($type->isBuiltIn()) {
            $schema->type = $this->typeToString($type);
        } elseif ($type->isEnum()) {
            $this->handleEnumType($type, $schema);
        } elseif ($type->matches(DateTimeInterface::class)) {
            $schema->type = 'string';
            $schema->format = 'date-time';
        } elseif ($type->isClass()) {
            $this->handleClassType($type, $schema);
        } else {
            throw new TypeNotSupported($type);
        }

        return $schema;
    }

    private function handleIterableType(TypeReflector $type, Schema $schema, ?ArrayMetadata $arrayMeta): void
    {
        $schema->type = 'array';

        if ($arrayMeta !== null) {
            $schema->items = $this->typeToSchema(
                new TypeReflector($arrayMeta->type),
            );
        }
    }

    private function handleEnumType(TypeReflector $type, Schema $schema): void
    {
        $schema->type = 'string';
        $schema->name = $type->getShortName();

        if ($type->isBackedEnum()) {
            $cases = array_map(
                fn($case) => $case->value,
                $type->getName()::cases(),
            );
            $schema->enum = $cases;

            return;
        }

        $cases = array_map(
            fn($case) => $case->name,
            $type->getName()::cases(),
        );
        $schema->enum = $cases;
    }

    private function handleClassType(TypeReflector $type, Schema $schema): void
    {
        $schema->name = $type->getShortName();
        $schema->type = 'object';
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

        $schema->properties = $properties;
    }

    private function shouldSkipProperty(PropertyReflector $propertyReflector, TypeReflector $parentType): bool
    {
        if ($parentType->matches(Request::class)) {
            // @TODO: probably shouldn't use this attribute for this purpose
            if ($propertyReflector->hasAttribute(SkipValidation::class)) {
                return true;
            }
        }

        return false;
    }

    private function typeToString(TypeReflector $type): string
    {
        if ($type->isBuiltIn()) {
            $type = $type->getName();

            if ($type === 'int') {
                return 'integer';
            }

            return $type;
        }

        return $type->getShortName();
    }
}
