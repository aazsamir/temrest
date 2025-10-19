<?php

declare(strict_types=1);

namespace Tests\Fixtures\Pet;

use Aazsamir\Temrest\Api\ApiResponse;
use Aazsamir\Temrest\Api\IsApiResponse;

class PetListResponse implements ApiResponse
{
    use IsApiResponse;

    /** @return Pet[] */
    public function toResponse(): array
    {
        throw new \Exception('');
    }
}
