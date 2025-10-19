<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\OpenApi\Metadata;

use Tempest\Reflection\ClassReflector;
use Tempest\Reflection\MethodReflector;
use Tempest\Reflection\TypeReflector;

// I thought that it would be easier and at some point I was too far deep into it to back out
class MetadataExtractor
{
    private const array CLASS_DEFINITIONS = [
        'class',
        'readonly class',
        'final class',
        'abstract class',
        'final readonly class',
        'readonly final class',
    ];

    /**
     * @var array<string, ClassMetadata>
     */
    private array $metadatas = [];

    public function getClassMetadata(ClassReflector $classReflector): ClassMetadata
    {
        $className = $classReflector->getName();

        if (isset($this->metadatas[$className])) {
            return $this->metadatas[$className];
        }

        $metadata = new ClassMetadata(type: $className);
        $uses = $this->getUseStatements($classReflector);

        $this->extractMethodsMetadata($classReflector, $metadata, $uses);
        $this->extractPropertiesMetadata($classReflector, $metadata, $uses);

        $this->metadatas[$className] = $metadata;

        return $metadata;
    }

    private function getUseStatements(ClassReflector $classReflector): array
    {
        $uses = [];
        $file = fopen($classReflector->getReflection()->getFileName(), 'r');

        try {
            while (($line = fgets($file)) !== false) {
                $parsedUse = $this->parseUseStatement($line);

                if ($parsedUse) {
                    [$shortClass, $fullClass] = $parsedUse;
                    $uses[$shortClass] = $fullClass;
                }

                if ($this->isClassDefinitionLine($line)) {
                    break;
                }
            }
        } finally {
            fclose($file);
        }

        return $uses;
    }

    /**
     * @return array{string, string}|null
     */
    private function parseUseStatement(string $line): ?array
    {
        // @TODO: handle `as` imports
        if (preg_match('/use\s+([^;]+);/', $line, $matches)) {
            $fullClass = trim($matches[1]);
            $shortClass = substr($fullClass, strrpos($fullClass, '\\') + 1);

            return [$shortClass, $fullClass];
        }

        return null;
    }

    private function isClassDefinitionLine(string $line): bool
    {
        foreach (self::CLASS_DEFINITIONS as $definition) {
            if (str_starts_with(trim($line), $definition . ' ')) {
                return true;
            }
        }

        return false;
    }

    private function extractMethodsMetadata(
        ClassReflector $classReflector,
        ClassMetadata $metadata,
        array $uses,
    ): void {
        foreach ($classReflector->getPublicMethods() as $methodReflector) {
            $this->extractMethodReturnTypeMetadata($methodReflector, $metadata, $uses);
            $this->extractMethodParametersMetadata($methodReflector, $metadata, $uses);
        }
    }

    private function extractMethodReturnTypeMetadata(
        MethodReflector $methodReflector,
        ClassMetadata $metadata,
        array $uses,
    ): void {
        $returnTypeMetadata = $this->getReturnTypeMetadata($methodReflector, $uses);

        if ($returnTypeMetadata) {
            $metadata->methods->setMethodReturnType(
                methodName: $methodReflector->getName(),
                returnType: $returnTypeMetadata,
            );
        }
    }

    private function extractMethodParametersMetadata(
        MethodReflector $methodReflector,
        ClassMetadata $metadata,
        array $uses,
    ): void {
        $parametersMetadata = $this->getParametersMetadata($methodReflector, $uses);

        foreach ($parametersMetadata as $parameterMetadata) {
            $metadata->addMethodParameter(
                methodName: $methodReflector->getName(),
                parameter: $parameterMetadata,
            );
        }
    }

    private function extractPropertiesMetadata(
        ClassReflector $classReflector,
        ClassMetadata $metadata,
        array $uses,
    ): void {
        foreach ($classReflector->getProperties() as $propertyReflector) {
            $propertyMetadata = $this->getPropertyMetadata($propertyReflector, $uses);

            if (!$propertyMetadata) {
                continue;
            }

            $metadata->properties->addProperty(
                new PropertyMetadata(
                    name: $propertyReflector->getName(),
                    type: $propertyMetadata,
                ),
            );
        }
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
