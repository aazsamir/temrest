<?php

declare(strict_types=1);

namespace Tests\Fixtures;

class UnionWithMixedArray
{
    /** @var string|mixed[] */
    public string|array $value;
}