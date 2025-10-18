<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\OpenApi;

use Aazsamir\Temrest\Api\ApiConfig;
use Aazsamir\Temrest\Api\ApiResponse;
use Aazsamir\Temrest\Api\Endpoint;
use Aazsamir\Temrest\OpenApi\Metadata\ArrayMetadata;
use Aazsamir\Temrest\OpenApi\Metadata\ClassMetadata;
use Aazsamir\Temrest\OpenApi\Metadata\MethodMetadata;
use Aazsamir\Temrest\OpenApi\Metadata\ParameterMetadata;
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
    private array $metadatas = [];

    public function __construct(
        private ApiConfig $config,
    ) {}

    public function generate(): OpenApi
    {
        $openapi = new OpenApi(
            info: new Info(
                title: $this->config->name,
            ),
        );

        $paths = [];

        foreach ($this->config->endpoints as $endpoint) {
            $path = new Path(
                path: $endpoint->route->uri,
                method: $endpoint->route->method->value,
            );
            $path->responses = $this->endpointToResponses($endpoint);
            $path->parameters = $this->endpointToParameters($endpoint);
            $path->requestBody = $this->endpointToRequestBody($endpoint);

            $paths[] = $path;
        }

        $openapi->paths = $paths;

        return $openapi;
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
        $classMetadata = $this->getClassMetadata($methodReflector->getDeclaringClass());
        $returnTypeReflector = $methodReflector->getReturnType();

        return $this->typeToSchema(
            $returnTypeReflector,
            $classMetadata->methods->getMethodReturnType($methodReflector->getName())
        );
    }

    private function typeToSchema(TypeReflector $type, ?ArrayMetadata $arrayMeta = null): Schema
    {
        $key = $type->getName() . ($type->isNullable() ? '|null' : '') . $arrayMeta?->docBlock();

        if (array_key_exists($key, $this->schemas)) {
            return $this->schemas[$key];
        }

        $schema = new Schema();
        $this->schemas[$key] = $schema;
        $schema->nullable = $type->isNullable();

        if ($type->isIterable()) {
            $schema->type = 'array';
            if ($arrayMeta !== null) {
                $schema->items = $this->typeToSchema(
                    new TypeReflector($arrayMeta->type),
                );
            }
        } elseif ($type->isBuiltIn()) {
            $schema->type = $this->typeToString($type);
        } elseif ($type->isEnum()) {
            $schema->type = 'string';
            $schema->name = $type->getShortName();

            if ($type->isBackedEnum()) {
                $cases = array_map(
                    fn($case) => $case->value,
                    $type->getName()::cases(),
                );
                $schema->enum = $cases;
            } else {
                $cases = array_map(
                    fn($case) => $case->name,
                    $type->getName()::cases(),
                );
                $schema->enum = $cases;
            }
        } elseif ($type->matches(DateTimeInterface::class)) {
            $schema->type = 'string';
            $schema->format = 'date-time';
        } elseif ($type->isClass()) {
            $schema->name = $type->getShortName();
            $schema->type = 'object';
            $properties = [];
            $classMeta = $this->getClassMetadata($type->asClass());

            foreach ($type->asClass()->getProperties() as $property) {
                if ($this->shouldSkipProperty($property, $type)) {
                    continue;
                }

                $arrayMeta = $classMeta->properties->getProperty($property->getName());
                $properties[$property->getName()] = $this->typeToSchema($property->getType(), $arrayMeta?->type);
            }

            $schema->properties = $properties;
        } else {
            dd('what we got here?', $type);
        }

        return $schema;
    }

    private function shouldSkipProperty(PropertyReflector $propertyReflector, TypeReflector $parentType): bool
    {
        if ($parentType->matches(Request::class)) {
            $requestReflector = new ClassReflector(Request::class);
            if ($requestReflector->hasProperty($propertyReflector->getName())) {
                return true;
            }
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

    // I thought that it would be easier and at some point I was too far deep into it to back out
    private function getClassMetadata(ClassReflector $classReflector): ClassMetadata
    {
        if (array_key_exists($classReflector->getName(), $this->metadatas)) {
            return $this->metadatas[$classReflector->getName()];
        }

        // parse `use Namespace\Class;` statements to resolve full class names
        $uses = [];
        $file = fopen($classReflector->getReflection()->getFileName(), 'r');

        while (($line = fgets($file)) !== false) {
            // @TODO: handle `as` imports
            if (preg_match('/use\s+([^;]+);/', $line, $matches)) {
                $fullClass = trim($matches[1]);
                $shortClass = substr($fullClass, strrpos($fullClass, '\\') + 1);
                $uses[$shortClass] = $fullClass;
            }

            $classDefinitions = [
                'class',
                'readonly class',
                'final class',
                'abstract class',
                'final readonly class',
                'readonly final class',
            ];

            foreach ($classDefinitions as $definition) {
                if (str_starts_with(trim($line), $definition . ' ')) {
                    // reached class definition, stop processing further
                    break 2;
                }
            }
        }

        fclose($file);

        $fullTypefn = function (string $type) use ($uses, $classReflector): string {
            $typeReflector = new TypeReflector($type);

            if ($typeReflector->isBuiltIn() || $type === 'mixed') {
                return $type;
            }
            if (array_key_exists($type, $uses)) {
                return $uses[$type];
            } elseif ($classReflector->getReflection()->getNamespaceName() !== null) {
                return $classReflector->getReflection()->getNamespaceName() . '\\' . $type;
            }

            return $type;
        };

        // @return Type[]
        $returnListRegex = '/@return\s+([^\s\[\]]+)(\[\])?/';
        // @return array<string, Type>
        $returnMapRegex = '/@return\s+array<([^\s\[\]]+),\s*([^\s\[\]]+)>/';
        // @return array<Type>
        $returnArrayRegex = '/@return\s+array<([^\s\[\]]+)>/';

        // @param Type[] $var
        $varListRegex = '/@(param|var)\s+([^\s\[\]<]+)(\[\])?\s+\$([^\s]+)/';
        // @param array<string, Type> $var
        $varMapRegex = '/@(param|var)\s+array<([^\s\[\]]+),\s*([^\s\[\]]+)>\\s+\$([^\s]+)/';
        // @param array<Type> $var
        $varArrayRegex = '/@(param|var)\s+array<([^\s\[\]]+)>\\s+\$([^\s]+)/';

        // $metadata = [];
        $metadata = new ClassMetadata(
            type: $classReflector->getName(),
        );

        foreach ($classReflector->getPublicMethods() as $methodReflector) {
            $docComment = $methodReflector->getReflection()->getDocComment();

            if ($docComment === false) {
                continue;
            }

            $docComment = \preg_replace('/[ ]+/', ' ', $docComment);

            $type = null;
            $regex = null;
            $key = null;

            if (\preg_match($returnMapRegex, $docComment, $matches)) {
                $key = $matches[1];
                $type = $matches[2];
                $regex = 'map';
            } elseif (preg_match($returnArrayRegex, $docComment, $matches)) {
                $type = $matches[1];
                $regex = 'array';
            } elseif (preg_match($returnListRegex, $docComment, $matches)) {
                $type = $matches[1];
                $regex = 'list';
            }

            if ($type !== null) {
                $metadata->methods->setMethodReturnType(
                    methodName: $methodReflector->getName(),
                    returnType: new ArrayMetadata(
                        type: $fullTypefn($type),
                        key: $key !== null ? $fullTypefn($key) : null,
                    ),
                );
            }

            // param parsing
            if (\preg_match_all($varListRegex, $docComment, $matches)) {
                foreach ($matches[0] as $index => $match) {
                    $varName = $matches[4][$index];
                    $varType = $matches[2][$index];
                    $metadata->addMethodParameter(
                        methodName: $methodReflector->getName(),
                        parameter: new ParameterMetadata(
                            name: $varName,
                            type: new ArrayMetadata(
                                type: $fullTypefn($varType),
                            ),
                        ),
                    );
                }
            }

            if (\preg_match_all($varMapRegex, $docComment, $matches)) {
                foreach ($matches[0] as $index => $match) {
                    $varName = $matches[4][$index];
                    $varType = $matches[3][$index];
                    $varKey = $matches[2][$index];
                    $metadata->addMethodParameter(
                        methodName: $methodReflector->getName(),
                        parameter: new ParameterMetadata(
                            name: $varName,
                            type: new ArrayMetadata(
                                type: $fullTypefn($varType),
                                key: $fullTypefn($varKey),
                            ),
                        ),
                    );
                }
            }

            if (\preg_match_all($varArrayRegex, $docComment, $matches)) {
                foreach ($matches[0] as $index => $match) {
                    $varName = $matches[3][$index];
                    $varType = $matches[2][$index];
                    $metadata->addMethodParameter(
                        methodName: $methodReflector->getName(),
                        parameter: new ParameterMetadata(
                            name: $varName,
                            type: new ArrayMetadata(
                                type: $fullTypefn($varType),
                            ),
                        ),
                    );
                }
            }
        }

        $this->metadatas[$classReflector->getName()] = $metadata;

        return $metadata;
    }
}
