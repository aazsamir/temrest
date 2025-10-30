<?php

declare(strict_types=1);

namespace Tests;

use Aazsamir\Temrest\OpenApi\Schema\Schema;
use PHPUnit\Framework\TestCase as FrameworkTestCase;

abstract class TestCase extends FrameworkTestCase
{
    protected function assertSchemas(Schema $expected, Schema $actual): void
    {
        $this->assertEquals($expected->toArray(false), $actual->toArray(false));
    }
}