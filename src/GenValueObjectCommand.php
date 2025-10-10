<?php

declare(strict_types=1);

namespace AtelliTech\Hyperf\Utils;

use AtelliTech\Hyperf\Utils\Database\MySQL\SchemaColumn;
use AtelliTech\Hyperf\Utils\Database\MySQL\SchemaReader;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;
use Hyperf\Stringable\Str;
use Symfony\Component\Console\Input\InputArgument;

#[Command(name: 'at:gen:vo', description: 'Generate Value Object of Enum from table')]
class GenValueObjectCommand extends AbstractGenCommand
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
        // $singularTable = Str::singular($table);
        // $prefix = Str::studly($singularTable);

        // generate value object
        $this->generateValueObject($domain, $table, $columns);
    }

    /**
     * generate the value object file.
     *
     * @param SchemaColumn[] $columns
     */
    protected function generateValueObject(string $domain, string $table, array $columns): void
    {
        $path = $this->basePath . "/app/Domain/{$domain}/ValueObject";
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $properties = [];
        $uses = [];
        foreach ($columns as $column) {
            $type = ($column->phpType === 'integer' ? 'int' : $column->phpType);

            // check is enum
            if (empty($column->enumValues)) {
                continue;
            }

            $cases = [];
            foreach ($column->enumValues as $idx => $value) {
                $value = (string) $value;
                $caseName = Str::upper(Str::snake($value, '_'));
                if (is_numeric($caseName[0])) {
                    $caseName = '_' . $caseName;
                }
                $cases[] = "    case {$caseName} = '{$value}';";
            }

            $name = Str::studly($column->name);
            $data = [
                'NAMESPACE' => "App\\Domain\\{$domain}\\ValueObject",
                'NAME' => $name,
                'PHPTYPE' => $type,
                'CASES' => implode("\n", $cases),
                'DESCRIPTION' => "/**\n * {$column->comment}\n */",
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

            $this->generateModelFileContent('ValueObject.stub', $data, $dest);
        }
    }
}
