<?php

declare(strict_types=1);

/**
 * This file is part of php-fast-forward/config.
 *
 * This source file is subject to the license bundled
 * with this source code in the file LICENSE.
 *
 * @copyright Copyright (c) 2025-2026 Felipe Sayão Lobato Abreu <github@mentordosnerds.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 *
 * @see       https://github.com/php-fast-forward/config
 * @see       https://github.com/php-fast-forward
 * @see       https://datatracker.ietf.org/doc/html/rfc2119
 */

namespace FastForward\Config\Tests\Stub;

final class ListConfigProvider
{
    public static int $invocations = 0;

    /**
     * @return array
     */
    public function __invoke(): array
    {
        ++self::$invocations;

        return [
            'console' => [
                'commands' => ['C', 'B'],
            ],
            'service_providers' => ['CP'],
        ];
    }
}
