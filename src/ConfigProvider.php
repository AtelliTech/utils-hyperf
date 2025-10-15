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
