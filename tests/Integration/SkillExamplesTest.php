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

namespace FastForward\Config\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * @internal
 */
#[CoversNothing]
final class SkillExamplesTest extends TestCase
{
    /**
     * @param string $example
     * @param string $expectedOutput
     *
     * @return void
     */
    #[Test]
    #[DataProvider('examples')]
    public function testConsumerExample(string $example, string $expectedOutput): void
    {
        $root = \dirname(__DIR__, 2);
        $process = new Process([
            \PHP_BINARY,
            $root . '/skills/fast-forward-config/examples/' . $example . '.php',
            $root . '/vendor/autoload.php',
        ], $root);
        $process->setTimeout(30);
        $process->run();

        self::assertTrue($process->isSuccessful(), $process->getErrorOutput());
        self::assertStringContainsString($expectedOutput, $process->getOutput());
    }

    /**
     * @return iterable
     */
    public static function examples(): iterable
    {
        yield 'composition' => ['composition', 'composition: ok'];
        yield 'sources' => ['sources', 'sources: ok'];
        yield 'caching' => ['caching', 'caching: ok'];
        yield 'containers' => ['containers', 'containers: ok'];
    }
}
