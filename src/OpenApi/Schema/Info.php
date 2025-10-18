<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\OpenApi\Schema;

class Info
{
    public function __construct(
        public string $title,
        public string $version = '1.0',
        public ?string $description = null,
        public ?string $termsOfService = null,
        public ?Contact $contact = null,
        public ?License $license = null,
    ) {}

    public function toArray(): array
    {
        return \array_filter([
            'title' => $this->title,
            'version' => $this->version,
            'description' => $this->description,
            'termsOfService' => $this->termsOfService,
            'contact' => $this->contact?->toArray(),
            'license' => $this->license?->toArray(),
        ]);
    }
}
