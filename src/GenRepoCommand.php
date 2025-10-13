<?php

declare(strict_types=1);

namespace AtelliTech\Hyperf\Utils;

use AtelliTech\Hyperf\Utils\Database\MySQL\SchemaReader;
use Hyperf\Command\Annotation\Command;
use Hyperf\DbConnection\Db;
use Symfony\Component\Console\Input\InputArgument;

#[Command(name: 'at:gen:repo', description: 'Generate Repository Interface and Repository Class')]
class GenRepoCommand extends AbstractGenCommand
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
        $className = $this->tableToClassName($table);

        // generate interface
        $this->generateInterface($className, $domain, $table, $columns);

        // generate repository class
        $this->generateRepository($className, $domain, $table, $columns);
    }

    /**
     * generate the repository file.
     *
     * @param array<string, mixed> $columns
     */
    protected function generateRepository(string $className, string $domain, string $table, array $columns): void
    {
        $path = $this->basePath . "/app/Infrastructure/{$domain}/Repository";
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $uses = [
            "App\\Domain\\{$domain}\\Repository\\{$className}RepoInterface",
            'AtelliTech\Hyperf\Utils\Core\AbstractRepository',
            'App\Model\\' . $className,
        ];

        $data = [
            'NAMESPACE' => "App\\Infrastructure\\{$domain}\\Repository",
            'CLASS' => $className,
            'USES' => 'use ' . implode(";\nuse ", $uses) . ';',
        ];

        $dest = $path . '/' . $className . 'Repo.php';
        if (file_exists($dest)) {
            echo "File already exists: {$dest}, overwrite it? (y/n)\n";
            $gets = (string) fgets(STDIN);
            $answer = trim($gets);
            if ($answer !== 'y') {
                return;
            }
        }

        $stub = 'Repository.stub';
        $this->generateModelFileContent($stub, $data, $dest);
    }

    /**
     * generate the interface file.
     *
     * @param array<string, mixed> $columns
     */
    protected function generateInterface(string $className, string $domain, string $table, array $columns): void
    {
        $path = $this->basePath . "/app/Domain/{$domain}/Repository";
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $data = [
            'NAMESPACE' => "App\\Domain\\{$domain}\\Repository",
            'CLASS' => $className,
        ];

        $dest = $path . '/' . $className . 'RepoInterface.php';
        if (file_exists($dest)) {
            echo "File already exists: {$dest}, overwrite it? (y/n)\n";
            $gets = (string) fgets(STDIN);
            $answer = trim($gets);
            if ($answer !== 'y') {
                return;
            }
        }

        $this->generateModelFileContent('Interface.stub', $data, $dest);
    }
}
