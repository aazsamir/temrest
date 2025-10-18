<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\OpenApi\Metadata;

class MethodsMetadata
{
    /**
     * @param MethodMetadata[] $methods
     */
    public function __construct(
        public array $methods = [],
    ) {}

    public function getMethodOrCreate(string $methodName): MethodMetadata
    {
        $method = $this->getMethod($methodName);

        if ($method === null) {
            $method = new MethodMetadata(
                name: $methodName,
            );

            $this->methods[] = $method;
        }

        return $method;
    }

    public function getMethod(string $methodName): ?MethodMetadata
    {
        foreach ($this->methods as $method) {
            if ($method->name === $methodName) {
                return $method;
            }
        }

        return null;
    }

    public function setMethodReturnType(string $methodName, ArrayMetadata $returnType): void
    {
        $method = $this->getMethodOrCreate($methodName);
        $method->returnType = $returnType;
    }

    public function getMethodReturnType(string $methodName): ?ArrayMetadata
    {
        $method = $this->getMethod($methodName);

        return $method?->returnType;
    }

    public function addMethodParameter(string $methodName, ParameterMetadata $parameter): void
    {
        $method = $this->getMethodOrCreate($methodName);
        $method->addParameter($parameter);
    }
}
