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

namespace FastForward\Config\Tests;

use Brick\VarExporter\VarExporter;
use FastForward\Config\ArrayAccessConfigTrait;
use FastForward\Config\ArrayConfig;
use FastForward\Config\Exception\InvalidArgumentException;
use FastForward\Config\Helper\ConfigHelper;
use FastForward\Config\LazyLoadConfigTrait;
use FastForward\Config\PhpFileConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/**
 * @internal
 */
#[CoversClass(PhpFileConfig::class)]
#[UsesClass(ArrayConfig::class)]
#[UsesClass(ConfigHelper::class)]
#[UsesClass(InvalidArgumentException::class)]
#[UsesTrait(LazyLoadConfigTrait::class)]
#[UsesTrait(ArrayAccessConfigTrait::class)]
final class PhpFileConfigResolverFunctionTest extends TestCase
{
    use PhpFileConfigFixture;

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function resolverCases(): iterable
    {
        foreach ([
            'str_contains',
            'str_starts_with',
            'str_ends_with',
            'strspn',
            'substr',
            'rtrim',
            'strncasecmp',
            'in_array',
        ] as $function) {
            foreach (['file', 'stream'] as $kind) {
                foreach (['get', 'set', 'remove'] as $operation) {
                    yield $function . ' ' . $kind . ' ' . $operation => [$function, $kind, $operation];
                }
            }

            yield $function . ' empty defaults' => [$function, 'empty', 'get'];
            yield $function . ' NUL defaults' => [$function, 'nul', 'get'];
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function localLookupCases(): iterable
    {
        foreach (['nested/file://settings.php', 'x://settings.php'] as $input) {
            foreach (['get', 'set', 'remove'] as $operation) {
                yield $input . ' ' . $operation => [$input, $operation];
            }
        }
    }

    /**
     * @param string $function
     * @param string $kind
     * @param string $operation
     *
     * @return void
     */
    #[Test]
    #[DataProvider('resolverCases')]
    public function testResolverUsesOnlyAvailableRequiredFunctions(
        string $function,
        string $kind,
        string $operation
    ): void {
        if (! \function_exists('proc_open') || '\\' === \DIRECTORY_SEPARATOR) {
            self::markTestSkipped('This matrix exercises POSIX resolution; native Windows has a dedicated consumer.');
        }

        mkdir($this->tempDirectory);
        $file = $this->tempDirectory . '/settings.php';
        $content = '<?php return ["initial" => true];';
        file_put_contents($file, $content);
        $map = $this->tempDirectory . '/class-map.json';
        file_put_contents($map, json_encode($this->resolverClassMap(), \JSON_THROW_ON_ERROR));
        $input = match ($kind) {
            'empty' => '',
            'stream' => 'file://' . $file,
            default => $file,
        };
        $required = 'empty' !== $kind && ('str_contains' === $function || ('nul' !== $kind && (
            'str_starts_with' === $function
            || ('str_ends_with' === $function && ('file' === $kind || 'get' !== $operation))
            || ('in_array' === $function && 'stream' === $kind)
        )));
        $result = json_decode($this->runPhpSubprocess([
            '-d', 'display_errors=stderr',
            '-d', 'disable_functions=' . $function,
            __DIR__ . '/Stub/disabled-resolver-functions.php', $map, $input, $operation, $function, $kind,
        ]), true, 512, \JSON_THROW_ON_ERROR);

        self::assertFalse($result['function_available']);
        self::assertSame(realpath(__DIR__ . '/../src/PhpFileConfig.php'), realpath($result['source']));
        if ($required) {
            self::assertSame(InvalidArgumentException::class, $result['exception'], $result['message'] ?? '');
            self::assertSame($content, file_get_contents($file));
            self::assertFileDoesNotExist($this->tempDirectory . '/.settings.php.lock');
        } else {
            self::assertNull($result['exception'], $result['message'] ?? '');
            if ('get' === $operation) {
                self::assertTrue($result['value']);
            }

            self::assertSame(match ($operation) {
                'set' => [
                    'initial' => true,
                    'saved' => true,
                ],
                'remove' => [],
                default => [
                    'initial' => true,
                ],
            }, require $file);
        }

        self::assertSame([], glob($this->tempDirectory . '/cfg_*'));
    }

    /**
     * @param string $input
     * @param string $operation
     *
     * @return void
     */
    #[Test]
    #[DataProvider('localLookupCases')]
    public function testLocalLookupDoesNotNeedWrapperMembership(string $input, string $operation): void
    {
        if (! \function_exists('proc_open') || '\\' === \DIRECTORY_SEPARATOR) {
            self::markTestSkipped('These local URI-like names require a POSIX filesystem.');
        }

        mkdir($this->tempDirectory . '/' . \dirname($input), 0o777, true);
        $file = $this->tempDirectory . '/' . $input;
        file_put_contents($file, '<?php return ["initial" => true];');
        $map = $this->tempDirectory . '/class-map.json';
        file_put_contents($map, json_encode($this->resolverClassMap(), \JSON_THROW_ON_ERROR));
        $result = json_decode($this->runPhpSubprocess([
            '-d', 'disable_functions=in_array',
            __DIR__ . '/Stub/disabled-resolver-functions.php', $map, $input, $operation, 'in_array', 'local',
            $this->tempDirectory,
        ]), true, 512, \JSON_THROW_ON_ERROR);

        self::assertFalse($result['function_available']);
        self::assertNull($result['exception'], $result['message'] ?? '');
        if ('get' === $operation) {
            self::assertTrue($result['value']);
        }

        self::assertSame(match ($operation) {
            'set' => [
                'initial' => true,
                'saved' => true,
            ],
            'remove' => [],
            default => [
                'initial' => true,
            ],
        }, require $file);
    }

    /**
     * @return void
     */
    #[Test]
    public function testNonFileRejectionDoesNotNeedTerminalSeparatorLookup(): void
    {
        if (! \function_exists('proc_open')) {
            self::markTestSkipped('Subprocess execution is unavailable.');
        }

        mkdir($this->tempDirectory);
        $map = $this->tempDirectory . '/class-map.json';
        file_put_contents($map, json_encode($this->resolverClassMap(), \JSON_THROW_ON_ERROR));
        $result = json_decode($this->runPhpSubprocess([
            '-d', 'disable_functions=str_ends_with',
            __DIR__ . '/Stub/disabled-resolver-functions.php', $map, $this->tempDirectory, 'get', 'str_ends_with',
        ]), true, 512, \JSON_THROW_ON_ERROR);

        self::assertFalse($result['function_available']);
        self::assertSame(InvalidArgumentException::class, $result['exception']);
        self::assertSame(['class-map.json'], array_values(array_diff(scandir($this->tempDirectory), ['.', '..'])));
    }

    /**
     * @return array<string, string>
     */
    private function resolverClassMap(): array
    {
        $map = [];
        $roots = [
            'FastForward\\Config\\' => realpath(__DIR__ . '/../src'),
            'Brick\\VarExporter\\' => \dirname((new ReflectionClass(VarExporter::class))->getFileName()),
        ];
        foreach ($roots as $prefix => $directory) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
                if ($file->isFile() && 'php' === $file->getExtension()) {
                    $relative = substr((string) $file->getPathname(), \strlen($directory) + 1, -4);
                    $map[$prefix . str_replace(\DIRECTORY_SEPARATOR, '\\', $relative)] = $file->getPathname();
                }
            }
        }

        return $map;
    }
}
