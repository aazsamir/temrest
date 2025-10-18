<?php

declare(strict_types=1);

use Aazsamir\Temrest\Api\ApiConfig;

use function Tempest\env;

return new ApiConfig(
    name: env('APPLICATION_NAME', 'Temrest') . ' API', 
);