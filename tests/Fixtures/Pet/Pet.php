<?php

declare(strict_types=1);

namespace Tests\Fixtures\Pet;

class Pet
{
    public int $id;
    public string $name;
    public PetType $type;
    public PetCuteness $cuteness;
    /** @var string[] */
    public array $tags;
}