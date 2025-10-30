<?php

declare(strict_types=1);

namespace Tests\Fixtures\Weird;

use Aazsamir\Temrest\Api\ApiResponse;
use Aazsamir\Temrest\Api\IsApiResponse;
use Tests\Fixtures\PlainObject;

class BResponse implements ApiResponse
{
    use IsApiResponse;

    public function toResponse(): PlainObject
    {
        throw new \LogicException('');
    }
}