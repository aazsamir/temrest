<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\OpenApi\Metadata;

class ClassMetadata
{
    public function __construct(
        public string $type = '',
        public MethodsMetadata $methods = new MethodsMetadata(),
        public PropertiesMetadata $properties = new PropertiesMetadata(),
    ) {}

    public function addMethodParameter(string $methodName, ParameterMetadata $parameter): void
    {
        $this->methods->addMethodParameter($methodName, $parameter);

        if ($methodName === '__construct') {
            $this->properties->addProperty(
                new PropertyMetadata(
                    name: $parameter->name,
                    type: $parameter->type,
                ),
            );
        }
    }
}
