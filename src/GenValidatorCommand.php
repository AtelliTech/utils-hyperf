<?php

declare(strict_types=1);

namespace AtelliTech\Hyperf\Utils;

use AtelliTech\Hyperf\Utils\Database\MySQL\SchemaColumn;
use AtelliTech\Hyperf\Utils\Database\MySQL\SchemaReader;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;
use Hyperf\Stringable\Str;
use Symfony\Component\Console\Input\InputArgument;

#[Command(name: 'at:gen:validator', description: 'Generate Validator from table')]
class GenValidatorCommand extends AbstractGenCommand
{
    /**
     * configure the command.
     */
    public function configure()
    {
        parent::configure();
        $this->addArgument('table', InputArgument::REQUIRED, 'Table Name');
        $this->addArgument('domain', InputArgument::OPTIONAL, 'Domain Name', 'Common');
        $this->addArgument('connection', InputArgument::OPTIONAL, 'Database Connection', 'default');
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // get db connection
        $connection = $this->input->getArgument('connection');
        $table = $this->input->getArgument('table');
        $domain = $this->input->getArgument('domain');
        $db = Db::connection($connection);
        $schemaReader = new SchemaReader($db);
        $columns = $schemaReader->getTableColumns($table);

        // generate validator
        $this->generateValidator($domain, $table, $columns);
    }

    /**
     * generate the validator file.
     *
     * @param SchemaColumn[] $columns
     */
    protected function generateValidator(string $domain, string $table, array $columns): void
    {
        $path = $this->basePath . "/app/Domain/{$domain}/Validation";
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $definitions = [];
        $defaults = [];
        foreach ($columns as $column) {
            $definition = [];

            // check type
            if ($column->phpType == 'integer') {
                $definition[] = 'integer:strict';
            } else {
                $definition[] = $column->phpType;
            }

            // check nullable
            if ($column->allowNull) {
                $definition[] = 'nullable';
            } else {
                $definition[] = 'required';
                $definition[] = 'filled';
                if (empty($column->enumValues)) {
                    $definition[] = 'between:1,' . $column->size;
                }
            }

            // check default
            if (!empty($column->defaultValue)) {
                $defaults[] = sprintf("            '%s' => %s", $column->name, var_export($column->defaultValue, true));
            }

            $definitions[] = sprintf("            '%s' => '%s'", $column->name, implode('|', $definition));
        }

        $name = Str::studly(Str::singular($table)) . 'Validator';
        $data = [
            'NAMESPACE' => "App\\Domain\\{$domain}\\Validation",
            'CLASS' => $name,
            'DEFINITIONS' => implode(",\n", $definitions),
            'DEFAULTS' => implode(",\n", $defaults),
        ];

        $dest = $path . '/' . $name . '.php';
        if (file_exists($dest)) {
            echo "File already exists: {$dest}, overwrite it? (y/n)\n";
            $gets = (string) fgets(STDIN);
            $answer = trim($gets);
            if ($answer !== 'y') {
                return;
            }
        }

        $this->generateModelFileContent('Validator.stub', $data, $dest);
    }
}
