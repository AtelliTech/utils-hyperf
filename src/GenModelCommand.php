<?php

declare(strict_types=1);

namespace AtelliTech\Hyperf\Utils;

use AtelliTech\Hyperf\Utils\Database\MySQL\SchemaReader;
use Exception;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;
use Hyperf\Stringable\Str;
use Symfony\Component\Console\Input\InputArgument;

#[Command(name: 'at:gen:model', description: 'Generate model from database table')]
class GenModelCommand extends AbstractGenCommand
{
    /**
     * configure the command.
     */
    public function configure()
    {
        parent::configure();
        $this->addArgument('table', InputArgument::REQUIRED, 'Table Name');
        $this->addArgument('namespace', InputArgument::OPTIONAL, 'Model Namespace', 'App\Model');
        // @phpstan-ignore-next-line
        $this->addArgument('path', InputArgument::OPTIONAL, 'Model Path', BASE_PATH . '/app/Model');
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
        $path = $this->input->getArgument('path');
        $namespace = $this->input->getArgument('namespace');
        $db = Db::connection($connection);
        $schemaReader = new SchemaReader($db);
        $columns = $schemaReader->getTableColumns($table);
        $className = $this->tableToClassName($table);

        echo "Columns for table {$table}:\n";
        echo 'Class Name: ' . $className . "\n";

        echo "\npath: {$path}\n";
        echo "namespace: {$namespace}\n\n";
        $uses = [
            'Hyperf\DbConnection\Model\Model',
            'OpenApi\Attributes as OA',
        ];
        $fillable = [];
        $casts = [];
        $annotations = [];
        $createdAt = 'null';
        $updatedAt = 'null';
        $relations = [];
        $defaults = [];
        $useTraits = [];
        $incrementing = 'false';
        $hidden = [];

        // determine fillable and casts
        foreach ($columns as $column) {
            // check fillable
            if (! $column->autoIncrement) {
                $fillable[] = $column->name;
            } else {
                $incrementing = 'true';
            }

            // check casts
            $casts[$column->name] = $column->phpType;

            // check created_at and updated_at
            if ($column->name == 'created_at') {
                $createdAt = "'created_at'";
            } elseif ($column->name == 'updated_at') {
                $updatedAt = "'updated_at'";
            }

            $attrs = [];
            $attrs['property'] = $column->name;
            $attrs['description'] = $column->comment ?? '';
            $attrs['type'] = $column->getAnnotationType();

            if (strpos($column->dbType, 'enum') !== false && ! empty($column->enumValues)) {
                $comments = explode(',', $column->comment ?? '');

                if (! isset($comments[1])) {
                    throw new Exception("Enum column {$column->name} missing comment for values mapping.");
                }

                $comment = trim($comments[1]);
                $enumMap = [];
                foreach (explode('|', $comment) as $part) {
                    [$key, $label] = explode(':', $part);
                    $enumMap[trim($key)] = trim($label);
                }
                $attrs['type'] = 'string';
                $attrs['enum'] = json_encode($column->enumValues, JSON_UNESCAPED_UNICODE);
            }

            if ($column->allowNull) {
                $attrs['nullable'] = true;
            }

            if (isset($column->defaultValue) && $column->defaultValue !== null && $column->defaultValue !== '' && preg_match('/^(created|updated)\_at$/', $column->name) == false) {
                $attrs['default'] = (string) $column->defaultValue;
                $defaults[$column->name] = $column->defaultValue;
            }

            if ($column->phpType == 'string' && isset($column->size)) {
                $attrs['maxLength'] = $column->size;
            }

            $annotation = "        new OA\\Property(\n";
            foreach ($attrs as $key => $value) {
                if ($key == 'enum') {
                    $annotation .= "            {$key}: {$value},\n";
                } elseif ($key == 'nullable') {
                    if ($value === true) {
                        $annotation .= "            {$key}: true,\n";
                    }
                } elseif ($key == 'default') {
                    $type = $attrs['type'];
                    if ($type == 'integer') {
                        $annotation .= "            {$key}: " . (int) $value . ",\n";
                    } elseif ($type == 'number') {
                        $annotation .= "            {$key}: " . (float) $value . ",\n";
                    } elseif ($type == 'boolean') {
                        $v = in_array(strtolower($value), ['1', 'true', 'yes']) ? 'true' : 'false';
                        $annotation .= "            {$key}: {$v},\n";
                    } else {
                        $annotation .= "            {$key}: \"{$value}\",\n";
                    }
                } else {
                    $annotation .= "            {$key}: \"{$value}\",\n";
                }
            }

            $annotation .= '        )';
            $annotations[] = $annotation;

            // check foreign key for relations
            if (preg_match('/^fk:(\w+)\.(\w+)$/', $column->comment, $matches) != false && ! in_array($column->name, ['created_by', 'updated_by'])) {
                $refTable = $matches[1];
                $refColumn = $matches[2];
                $methodName = Str::singular($refTable);
                $refClass = $this->tableToClassName($refTable);
                $relation = "    /**\n";
                $relation .= "     * Get the {$methodName} that owns the {$className}.\n";
                $relation .= "     */\n";
                $relation .= "    public function {$methodName}() {\n";
                $relation .= "        return \$this->belongsTo({$refClass}::class, '{$column->name}', '{$refColumn}');\n";
                $relation .= '    }';
                $relations[] = $relation;
            }

            if ($column->name == 'created_by' || $column->name == 'updated_by') {
                if ($column->name == 'created_by') {
                    $useTraits[] = '    use CreatorTrait;';
                } else {
                    $useTraits[] = '    use UpdaterTrait;';
                }

                $hidden[] = $column->name;
            }
        }

        $data = [
            'NAMESPACE' => $namespace,
            'CLASS' => $className,
            'TABLE' => $table,
            'CONNECTION' => $connection,
            'USES' => implode(";\n", array_map(fn ($u) => "use {$u}", $uses)) . ';',
            'INHERITANCE' => 'Model',
            'FILLABLE' => "['" . implode("', '", $fillable) . "']",
            'CASTS' => "[\n        " . implode(",\n        ", array_map(fn ($k, $v) => "'{$k}' => '{$v}'", array_keys($casts), $casts)) . "\n    ]",
            'ANNOTATION' => "\n" . implode(",\n", $annotations),
            'CREATEDAT' => $createdAt,
            'UPDATEDAT' => $updatedAt,
            'RELATIONS' => implode("\n\n", $relations),
            'DEFAULTS' => ! empty($defaults) ? "    /**\n     * The default attributes for the model.\n     */\n    protected array \$attributes = [\n        " . implode(",\n        ", array_map(fn ($k, $v) => "'{$k}' => " . (is_string($v) ? "'{$v}'" : $v), array_keys($defaults), $defaults)) . "\n    ];" : '',
            'TRAITS' => ! empty($useTraits) ? implode("\n", $useTraits) . "\n" : '',
            'INCREMENTING' => $incrementing,
            'HIDDEN' => ! empty($hidden) ? "['" . implode("', '", $hidden) . "']" : '[]',
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

        $this->generateModelFileContent('Model.stub', $data, $dest);
    }
}
