<?php

declare(strict_types=1);

namespace AtelliTech\Hyperf\Utils;

use Exception;
use Hyperf\Command\Command;
use Hyperf\Stringable\Str;

abstract class AbstractGenCommand extends Command
{
    /**
     * @var string
     */
    protected $basePath = BASE_PATH; // @phpstan-ignore-line

    /**
     * generate the model file content.
     *
     * @param array<string, mixed> $data
     * @throws Exception
     */
    protected function generateModelFileContent(string $stub, array $data, string $destination): void
    {
        $stub = file_get_contents(__DIR__ . '/Stubs/' . $stub);
        if ($stub === false) {
            throw new Exception('Failed to read stub file.');
        }

        foreach ($data as $key => $value) {
            $stub = str_replace("%{$key}%", $value, $stub);
        }

        file_put_contents($destination, $stub);
        echo "Model file generated: {$destination}\n";
    }

    /**
     * convert table name into class name.
     */
    protected function tableToClassName(string $table): string
    {
        $names = explode('_', $table);
        $names = array_map(fn ($name) => Str::singular($name), $names);
        $names = array_map(fn ($name) => Str::studly($name), $names);
        return implode('', $names);
    }
}
