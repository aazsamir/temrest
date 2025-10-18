<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\Api;

use Tempest\Http\Response;

interface ApiResponse extends Response
{
    public function toResponse(): mixed;
}
