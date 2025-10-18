<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\OpenApi;

use Tempest\Reflection\TypeReflector;

class TypeNotSupported extends \Exception
{
    public function __construct(TypeReflector $type)
    {
        parent::__construct(sprintf(
            'The type "%s" is currently not supported :(',
            $type->getName(),
        ));
    }
}
