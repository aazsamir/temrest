<?php

declare(strict_types=1);

namespace Tests\Fixtures\Pet;

use Tempest\Http\IsRequest;
use Tempest\Http\Request;

class PetListRequest implements Request
{
    use IsRequest;

    public ?int $page = 1;
}