<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\OpenApi\Metadata;

use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;
use Tempest\Reflection\TypeReflector;

// I thought that it would be easier and at some point I was too far deep into it to back out
class MetadataExtractor
{
    /**
     * @var array<string, ClassMetadata>
     */
    private array $metadatas = [];

    public function getClassMetadata(ClassReflector $classReflector): ClassMetadata
    {
        if (array_key_exists($classReflector->getName(), $this->metadatas)) {
            return $this->metadatas[$classReflector->getName()];
        }

        $uses = $this->getUseStatements($classReflector);

        // $metadata = [];
        $metadata = new ClassMetadata(
            type: $classReflector->getName(),
        );

        foreach ($classReflector->getPublicMethods() as $methodReflector) {
            $returnTypeMetadata = $this->getReturnTypeMetadata($methodReflector, $uses);

            if ($returnTypeMetadata) {
                $metadata->methods->setMethodReturnType(
                    methodName: $methodReflector->getName(),
                    returnType: $returnTypeMetadata,
                );
            }

            $parametersMetadata = $this->getParametersMetadata($methodReflector, $uses);

            foreach ($parametersMetadata as $parameterMetadata) {
                $metadata->addMethodParameter(
                    methodName: $methodReflector->getName(),
                    parameter: $parameterMetadata,
                );
            }
        }

        foreach ($classReflector->getProperties() as $propertyReflector) {
            $propertyMetadata = $this->getPropertyMetadata($propertyReflector, $uses);

            if ($propertyMetadata) {
                $metadata->properties->addProperty(
                    new PropertyMetadata(
                        name: $propertyReflector->getName(),
                        type: $propertyMetadata,
                    ),
                );
            }
        }

        $this->metadatas[$classReflector->getName()] = $metadata;

        return $metadata;
    }

    private function getUseStatements(ClassReflector $classReflector): array
    {
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

        return $uses;
    }

    private function getReturnTypeMetadata(MethodReflector $methodReflector, array $uses): ?ArrayMetadata
    {
        $docComment = $this->getMethodDocComment($methodReflector);

        if (!$docComment) {
            return null;
        }

        // @return Type[]
        $returnListRegex = '/@return\s+([^\s\[\]]+)(\[\])?/';
        // @return array<string, Type>
        $returnMapRegex = '/@return\s+array<([^\s\[\]]+),\s*([^\s\[\]]+)>/';
        // @return array<Type>
        $returnArrayRegex = '/@return\s+array<([^\s\[\]]+)>/';

        $type = null;
        $key = null;

        if (\preg_match($returnMapRegex, $docComment, $matches)) {
            $key = $matches[1];
            $type = $matches[2];
        } elseif (preg_match($returnArrayRegex, $docComment, $matches)) {
            $type = $matches[1];
        } elseif (preg_match($returnListRegex, $docComment, $matches)) {
            $type = $matches[1];
        }

        if ($type === null) {
            return null;
        }

        return new ArrayMetadata(
            type: $this->shortToFullType($type, $uses, $methodReflector->getDeclaringClass()),
            key: $key !== null ? $this->shortToFullType($key, $uses, $methodReflector->getDeclaringClass()) : null,
        );
    }

    private function getParametersMetadata(MethodReflector $methodReflector, array $uses): array
    {
        $docComment = $this->getMethodDocComment($methodReflector);

        if (!$docComment) {
            return [];
        }

        $parameters = [];

        // @param Type[] $var
        $varListRegex = '/@(param|var)\s+([^\s\[\]<]+)(\[\])?\s+\$([^\s]+)/';
        // @param array<string, Type> $var
        $varMapRegex = '/@(param|var)\s+array<([^\s\[\]]+),\s*([^\s\[\]]+)>\\s+\$([^\s]+)/';
        // @param array<Type> $var
        $varArrayRegex = '/@(param|var)\s+array<([^\s\[\]]+)>\\s+\$([^\s]+)/';

        if (\preg_match_all($varListRegex, $docComment, $matches)) {
            foreach ($matches[0] as $index => $_) {
                $varName = $matches[4][$index];
                $varType = $matches[2][$index];
                $parameters[] = new ParameterMetadata(
                    name: $varName,
                    type: new ArrayMetadata(
                        type: $this->shortToFullType($varType, $uses, $methodReflector->getDeclaringClass()),
                    ),
                );
            }
        }

        if (\preg_match_all($varMapRegex, $docComment, $matches)) {
            foreach ($matches[0] as $index => $_) {
                $varName = $matches[4][$index];
                $varType = $matches[3][$index];
                $varKey = $matches[2][$index];
                $parameters[] = new ParameterMetadata(
                    name: $varName,
                    type: new ArrayMetadata(
                        type: $this->shortToFullType($varType, $uses, $methodReflector->getDeclaringClass()),
                        key: $this->shortToFullType($varKey, $uses, $methodReflector->getDeclaringClass()),
                    ),
                );
            }
        }

        if (\preg_match_all($varArrayRegex, $docComment, $matches)) {
            foreach ($matches[0] as $index => $_) {
                $varName = $matches[3][$index];
                $varType = $matches[2][$index];
                $parameters[] = new ParameterMetadata(
                    name: $varName,
                    type: new ArrayMetadata(
                        type: $this->shortToFullType($varType, $uses, $methodReflector->getDeclaringClass()),
                    ),
                );
            }
        }

        return $parameters;
    }

    private function getMethodDocComment(MethodReflector $methodReflector): ?string
    {
        $docComment = $methodReflector->getReflection()->getDocComment();

        if ($docComment === false) {
            return null;
        }

        return \preg_replace('/[ ]+/', ' ', $docComment);
    }

    private function shortToFullType(string $type, array $uses, ClassReflector $classReflector): string
    {
        $typeReflector = new TypeReflector($type);

        if ($typeReflector->isBuiltIn() || $type === 'mixed') {
            return $type;
        }

        if (array_key_exists($type, $uses)) {
            return $uses[$type];
        }

        return $classReflector->getReflection()->getNamespaceName() . '\\' . $type;
    }

    private function getPropertyMetadata(
        \Tempest\Reflection\PropertyReflector $propertyReflector,
        array $uses,
    ): ?ArrayMetadata {
        $docComment = $propertyReflector->getReflection()->getDocComment();

        if (!$docComment) {
            return null;
        }

        // @var Type[]
        $varListRegex = '/@var\s+([^\s\[\]<]+)(\[\])?/';
        // @var array<string, Type>
        $varMapRegex = '/@var\s+array<([^\s\[\]]+),\s*([^\s\[\]]+)>/';
        // @var array<Type>
        $varArrayRegex = '/@var\s+array<([^\s\[\]]+)>/';

        $type = null;
        $key = null;

        if (\preg_match($varMapRegex, $docComment, $matches)) {
            $key = $matches[1];
            $type = $matches[2];
        } elseif (preg_match($varArrayRegex, $docComment, $matches)) {
            $type = $matches[1];
        } elseif (preg_match($varListRegex, $docComment, $matches)) {
            $type = $matches[1];
        }

        if ($type === null) {
            return null;
        }

        return new ArrayMetadata(
            type: $this->shortToFullType($type, $uses, $propertyReflector->getClass()),
            key: $key !== null ? $this->shortToFullType($key, $uses, $propertyReflector->getClass()) : null,
        );
    }
}
