<?php

declare(strict_types=1);

namespace Tests\Fixtures\Weird;

use Tempest\Http\IsRequest;
use Tempest\Http\Request;

class FullArrayTypeResponse implements Request
{
    use IsRequest;

    /** @var \Tests\Fixtures\PlainObject[] */
    public array $fullTyped;
}
