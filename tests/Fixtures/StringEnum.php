<?php

declare(strict_types=1);

namespace Tests\Fixtures;

enum StringEnum: string
{
    case FOO = 'foo';
    case BAR = 'bar';
    case BAZ = 'baz';
}