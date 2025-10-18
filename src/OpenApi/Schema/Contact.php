<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\OpenApi\Schema;

class Contact
{
    public function __construct(
        public ?string $name = null,
        public ?string $url = null,
        public ?string $email = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'url' => $this->url,
            'email' => $this->email,
        ]);
    }
}
