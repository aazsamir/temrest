<?php

declare(strict_types=1);

namespace Tests\Fixtures\Pet;

use Tempest\Http\IsRequest;
use Tempest\Http\Request;

class PetStoreRequest implements Request
{
    use IsRequest;

    public string $name;
    public PetType $type;
    public PetCuteness $cuteness;
    /** @var string[] */
    public array $tags;
}