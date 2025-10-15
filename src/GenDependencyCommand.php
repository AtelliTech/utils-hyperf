<?php

declare(strict_types=1);

namespace AtelliTech\Hyperf\Utils;

use Hyperf\Command\Annotation\Command;
use Hyperf\Stringable\Str;
use Symfony\Component\Console\Input\InputArgument;
use ReflectionClass;

#[Command(name: 'at:gen:di', description: 'Scan repository and service to generate dependencies')]
class GenDependencyCommand extends AbstractGenCommand
{
    /**
     * configure the command.
     */
    public function configure()
    {
        parent::configure();
        $this->addArgument('path', InputArgument::OPTIONAL, '預設: ' . BASE_PATH . '/app', BASE_PATH . '/app'); // @phpstan-ignore-line
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // scan all entries in the path
        $path = (string) $this->input->getArgument('path');
        $output = shell_exec("find {$path} -type f");
        if (empty($output)) {
            $this->error('No file found in path: ' . $path);
            return;
        }
        $entries = explode("\n", trim($output));
        $services = [];
        $repos = [];
        foreach($entries as $entry) {
            if (preg_match('/(Repo|Service)\.php$/', $entry, $matches) == false) {
                continue;
            }

            $class = Str::of($entry)
                ->replace(BASE_PATH . '/', '') // @phpstan-ignore-line
                ->replace('/', '\\')
                ->replace('.php', '')
                ->replace('app\\', 'App\\')
                ->__toString();

            echo "Processing {$class}\n";

            if ($matches[1] == 'Repo') {
                $interface = $class . 'Interface';
                $repos[$interface] = $class;
            } else if ($matches[1] == 'Service') {
                $services[$class] = $class;
            }
        }

        ksort($repos);
        ksort($services);
        $dependencies = array_merge($repos, $services);
        $depLines = [];
        foreach ($dependencies as $interface => $implementation) {
            $depLines[] = "    \\{$interface}::class => \\{$implementation}::class,";
        }

        $depContent = "<?php\n\n";
        $depContent .= "declare(strict_types=1);\n\n";
        $depContent .= "return [\n";
        $depContent .= implode("\n", $depLines) . "\n";
        $depContent .= "];\n";
        file_put_contents(BASE_PATH . '/config/autoload/autoload_dependencies.php', $depContent); // @phpstan-ignore-line
        $this->info('Generated dependencies to ' . BASE_PATH . '/config/autoload/autoload_dependencies.php'); // @phpstan-ignore-line
    }
}
