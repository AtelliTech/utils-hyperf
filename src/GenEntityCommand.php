<?php

declare(strict_types=1);

namespace AtelliTech\Hyperf\Utils;

use AtelliTech\Hyperf\Utils\Database\MySQL\SchemaColumn;
use AtelliTech\Hyperf\Utils\Database\MySQL\SchemaReader;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;
use Hyperf\Stringable\Str;
use Symfony\Component\Console\Input\InputArgument;

#[Command(name: 'at:gen:entity', description: 'Generate Entity from table')]
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

        // generate entity
        $this->generateEntity($domain, $table, $columns);
    }

    /**
     * generate the entity file.
     *
     * @param SchemaColumn[] $columns
     */
    protected function generateEntity(string $domain, string $table, array $columns): void
    {
        $path = $this->basePath . "/app/Domain/{$domain}/Entity";
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $args = [];
        $toArrays = [];
        $uses = [];
        foreach ($columns as $column) {
            // check is enum
            if (empty($column->enumValues)) {
                $type = ($column->phpType === 'integer' ? 'int' : $column->phpType);
                $args[] = sprintf('private %s $%s', $type, $column->name);
                $toArrays[] = sprintf('\'%s\' => $this->%s', $column->name, $column->name);
                continue;
            }

            $name = Str::studly($column->name);
            $args[] = sprintf('private %s $%s', $name, $column->name);
            $toArrays[] = sprintf('\'%s\' => $this->%s->value', $column->name, $column->name);
            $uses[] = "use App\\Domain\\{$domain}\\ValueObject\\{$name};";

            $dest = $path . '/' . $name . '.php';
            if (file_exists($dest)) {
                echo "File already exists: {$dest}, overwrite it? (y/n)\n";
                $gets = (string) fgets(STDIN);
                $answer = trim($gets);
                if ($answer !== 'y') {
                    return;
                }
            }
        }

        $className = $this->tableToClassName($table);
        $data = [
            'NAMESPACE' => "App\\Domain\\{$domain}\\Entity",
            'USES' => implode("\n", $uses),
            'CLASS' => $className,
            'ARGS' => implode(",\n            ", $args),
            'TOARRAYS' => implode(",\n            ", $toArrays),
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
