<?php

declare(strict_types=1);

namespace AtelliTech\Hyperf\Utils;

use AtelliTech\Hyperf\Utils\Database\MySQL\SchemaReader;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;
use Hyperf\Stringable\Str;
use Symfony\Component\Console\Input\InputArgument;

#[Command(name: 'at:gen:entity', description: 'Generate model from database table')]
class GenEntityCommand extends AbstractGenCommand
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
        $singularTable = Str::singular($table);
        $className = Str::studly($singularTable);

        // generate entity
        $this->generateEntity($className, $domain, $table, $columns);
    }

    /**
     * generate the entity file.
     */
    protected function generateEntity(string $className, string $domain, string $table, array $columns): void
    {
        $path = $this->basePath . "/app/Domain/{$domain}/Entity";
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $properties = [];
        $uses = [];
        foreach ($columns as $column) {
            $type = ($column->phpType === 'integer' ? 'int' : $column->phpType);

            // check is enum
            if (! empty($column->enumValues)) {
                $ovClass = Str::studly($column->name);
                $type = $ovClass;
                $uses[] = 'App\Domain\\' . $domain . '\ValueObject\\' . $ovClass;
            }

            $property = $type . ' $' . $column->name;

            // check has default value
            if (! empty($column->defaultValue) && empty($column->enumValues)) {
                $property .= ' = ' . var_export($column->defaultValue, true) . ';';
            } else {
                $property .= ';';
            }

            if ($column->allowNull) {
                $property = '?' . $property;
            }

            $properties[] = '    protected ' . $property;
        }

        $properties = implode("\n\n", $properties);

        $data = [
            'NAMESPACE' => "App\\Domain\\{$domain}\\Entity",
            'CLASS' => $className,
            'PROPERTIES' => $properties,
            'USES' => ! empty($uses) ? 'use ' . implode(";\nuse ", $uses) . ';' : '',
        ];

        $dest = $path . '/' . $className . '.php';
        if (file_exists($dest)) {
            echo "File already exists: {$dest}, overwrite it? (y/n)\n";
            $gets = (string) fgets(STDIN);
            $answer = trim($gets);
            if ($answer !== 'y') {
                return;
            }
        }

        $this->generateModelFileContent('Entity.stub', $data, $dest);
    }
}
