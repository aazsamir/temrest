<?php

declare(strict_types=1);

namespace Aazsamir\Temrest\OpenApi;

use Tempest\Console\ConsoleCommand;
use Tempest\Console\HasConsole;
use Tempest\Highlight\Languages\Json\JsonLanguage;

class GenerateSchemaCommand
{
    use HasConsole;

    public function __construct(
        private SchemaGenerator $schemaGenerator,
    ) {}

    #[ConsoleCommand(name: 'openapi:generate', description: 'Generate OpenAPI schema')]
    public function __invoke(
        ?string $output = './openapi.json',
        bool $print = false,
        bool $pretty = false,
        bool $debug = false,
    ): void {
        $schema = $this->schemaGenerator->generate();

        if ($output) {
            \file_put_contents($output, \json_encode($schema->toArray(), JSON_PRETTY_PRINT) . "\n");
        }

        if ($debug) {
            dump($schema);
        }

        if ($print || $pretty) {
            if ($pretty) {
                $this->console->writeWithLanguage(
                    json_encode(
                        $schema->toArray(),
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                    ),
                    new JsonLanguage(),
                );
            } else {
                echo \json_encode($schema->toArray()) . "\n";
            }
        }
    }
}
