<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\Api;

use UnitEnum;

trait ToArray
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->serializeArray(get_object_vars($this));
    }

    private function serializeArray(array $data): array
    {
        $array = [];

        foreach ($data as $name => $value) {
            $array[$name] = $this->serializeValue($value);
        }

        return $array;
    }

    private function serializeValue(mixed $value): mixed
    {
        if (\is_object($value) && \method_exists($value, 'toArray')) {
            return $value->toArray();
        } elseif ($value instanceof \BackedEnum) {
            return $value->value;
        } elseif ($value instanceof \UnitEnum) {
            return $value->name;
        } elseif (\is_array($value) && $value !== []) {
            return array_map($this->serializeValue(...), $value);
        } elseif ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        } elseif (is_object($value)) {
            return $this->serializeArray(get_object_vars($value));
        } else {
            return $value;
        }
    }
}
