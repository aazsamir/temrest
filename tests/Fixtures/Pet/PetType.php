<?php

declare(strict_types=1);

namespace Tests\Fixtures\Pet;

enum PetType: string
{
    case Dog = 'dog';
    case Cat = 'cat';
}