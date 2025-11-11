<?php

declare(strict_types=1);

namespace Tests\Fixtures;

class NullInUnion
{
    public string|int|null $value;
}