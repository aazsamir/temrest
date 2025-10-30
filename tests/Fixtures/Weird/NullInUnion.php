<?php

declare(strict_types=1);

namespace Tests\Fixtures\Weird;

class NullInUnion
{
    public string|int|null $value;
}