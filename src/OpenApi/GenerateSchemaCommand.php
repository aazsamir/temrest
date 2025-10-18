<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\OpenApi;

use Tempest\Console\ConsoleCommand;

class GenerateSchemaCommand
{
    public function __construct(
        private SchemaGenerator $schemaGenerator,
    ) {}

    #[ConsoleCommand(name: 'openapi:generate', description: 'Generate OpenAPI schema')]
    public function __invoke()
    {
        $schema = $this->schemaGenerator->generate();

        // dd($schema);

        dump($schema, $schema->toArray());
        echo \json_encode($schema->toArray()) . "\n";
    }
}
