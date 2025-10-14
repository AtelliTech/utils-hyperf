<?php

declare(strict_types=1);

namespace AtelliTech\Hyperf\Utils;

use Hyperf\Command\Annotation\Command;
use Symfony\Component\Console\Input\InputArgument;

#[Command(name: 'at:gen:apidoc', description: 'Generate API documentation page')]
class GenApiSpecCommand extends AbstractGenCommand
{
    /**
     * configure the command.
     */
    public function configure()
    {
        parent::configure();
        $this->addArgument('namespace', InputArgument::OPTIONAL, 'API Spec Controller Namespace', 'App\Controller');
        // @phpstan-ignore-next-line
        $this->addArgument('path', InputArgument::OPTIONAL, 'API Spec Controller Path', BASE_PATH . '/app/Controller');
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $path = $this->input->getArgument('path');
        $namespace = $this->input->getArgument('namespace');

        echo "\npath: {$path}\n";
        echo "namespace: {$namespace}\n\n";

        $data = [
            'NAMESPACE' => $namespace,
        ];
        $dest = $path . '/OpenApiSpecController.php';
        if (file_exists($dest)) {
            echo "File already exists: {$dest}, overwrite it? (y/n)\n";
            $gets = (string) fgets(STDIN);
            $answer = trim($gets);
            if ($answer !== 'y') {
                return;
            }
        }

        $this->generateModelFileContent('OpenApiSpecController.stub', $data, $dest);

        // echo success message and teach how to register route
        echo "\nGenerate API Spec Controller successfully: {$dest}\n";
        echo "Please register route in `config/routes.php`:\n";
        echo "----------------------------------------\n";
        echo "use {$namespace}\\OpenApiSpecController;\n";
        echo "\n";
        echo "\$router->addRoute(['GET'], '/api/doc', [OpenApiSpecController::class, 'index']);\n";
        echo "----------------------------------------\n\n";
    }
}
