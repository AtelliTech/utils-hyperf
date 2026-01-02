<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @see     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace AtelliTech\Hyperf\Utils;

class ConfigProvider
{
    /**
     * Return config.
     *
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config file for utils-hyperf.',
                    'source' => __DIR__ . '/../publish/audit_log.php',
                    'destination' => BASE_PATH . '/config/autoload/audit_log.php',
                ],
            ],
            'dependencies' => [
            ],
            'commands' => [
                GenApiSpecCommand::class,
                GenDependencyCommand::class,
                GenEntityCommand::class,
                GenModelCommand::class,
                GenRepoCommand::class,
                GenValidatorCommand::class,
                GenValueObjectCommand::class,
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
        ];
    }
}
