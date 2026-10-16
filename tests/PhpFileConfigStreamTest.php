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

use FastForward\Config\ArrayAccessConfigTrait;
use FastForward\Config\ArrayConfig;
use FastForward\Config\Exception\InvalidArgumentException;
use FastForward\Config\Helper\ConfigHelper;
use FastForward\Config\LazyLoadConfigTrait;
use FastForward\Config\PhpFileConfig;
use FastForward\Config\Tests\Stub\FileProxyStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesTrait;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PhpFileConfig::class)]
#[UsesClass(ArrayConfig::class)]
#[UsesClass(ConfigHelper::class)]
#[UsesClass(InvalidArgumentException::class)]
#[UsesTrait(LazyLoadConfigTrait::class)]
#[UsesTrait(ArrayAccessConfigTrait::class)]
final class PhpFileConfigStreamTest extends TestCase
{
    use PhpFileConfigFixture;

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function relativePathsContainingUriSyntax(): iterable
    {
        yield 'nested scheme-like component' => ['nested/file://settings.php', null];
        yield 'unregistered numeric prefix' => ['9file://settings.php', null];
        yield 'scheme cannot contain an underscore' => ['file_name://settings.php', null];
        yield 'unregistered scheme uses the native filesystem fallback' => [
            'ff-config-unregistered://settings.php',
            null,
        ];
        yield 'single-character registered name still uses filesystem' => ['x://settings.php', 'x'];
        yield 'lowercase does not select an uppercase registration' => ['upper://settings.php', 'UPPER'];
        yield 'mixed case does not select an uppercase registration' => ['UpPeR://settings.php', 'UPPER'];
    }

    /**
     * @return void
     */
    #[Test]
    #[DataProvider('relativePathsContainingUriSyntax')]
    public function testRelativePathsContainingUriSyntaxKeepTheirInitialLocation(
        string $relative,
        ?string $registered
    ): void {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            self::markTestSkipped('POSIX permits colon characters in filesystem path components.');
        }

        $source = $this->tempDirectory . '/source';
        $other = $this->tempDirectory . '/other';
        $selected = $source . '/' . $relative;
        $competing = $other . '/' . $relative;
        mkdir(\dirname($selected), 0o777, true);
        mkdir(\dirname($competing), 0o777, true);
        file_put_contents($selected, '<?php chdir(' . var_export($other, true) . '); return ["initial" => true];');
        $competingContent = '<?php return ["competing" => true];';
        file_put_contents($competing, $competingContent);
        $workingDirectory = getcwd();
        if (null !== $registered) {
            self::assertTrue(stream_wrapper_register($registered, FileProxyStream::class));
        }

        try {
            chdir($source);
            $config = new PhpFileConfig($relative, persistent: true);
            self::assertTrue($config->get('initial'));
            self::assertSame(realpath($other), getcwd());
            $config->set('saved', true);
            $config->remove('initial');

            self::assertSame([
                'saved' => true,
            ], require $selected);
            self::assertSame($competingContent, file_get_contents($competing));
            self::assertFileExists(\dirname($selected) . '/.settings.php.lock');
            self::assertFileDoesNotExist(\dirname($competing) . '/.settings.php.lock');
        } finally {
            chdir($workingDirectory);
            if (null !== $registered) {
                stream_wrapper_unregister($registered);
            }
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function registeredFileStreams(): iterable
    {
        yield 'numeric leading protocol' => ['9ffconfig', '9ffconfig'];
        yield 'exact uppercase registration' => ['FFCONFIGTEST', 'FFCONFIGTEST'];
        yield 'lowercase registration accepts mixed-case input' => ['ffconfigtest', 'FfConfigTest'];
    }

    /**
     * @return void
     */
    #[Test]
    #[DataProvider('registeredFileStreams')]
    public function testRegisteredFileStreamsKeepNativeProtocolSelection(string $registered, string $requested): void
    {
        file_put_contents($this->tempFile, '<?php return ["initial" => true];');
        self::assertTrue(stream_wrapper_register($registered, FileProxyStream::class));
        $input = $requested . '://' . $this->tempFile;
        try {
            self::assertSame([
                'initial' => true,
            ], require $input);
            self::assertTrue((new PhpFileConfig($input))->get('initial'));
        } finally {
            stream_wrapper_unregister($registered);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function leadingFileStreams(): iterable
    {
        yield 'lowercase scheme' => ['file'];
        yield 'uppercase scheme' => ['FILE'];
    }

    /**
     * @return void
     */
    #[Test]
    #[DataProvider('leadingFileStreams')]
    public function testExplicitLeadingFileStreamRemainsReadable(string $scheme): void
    {
        file_put_contents($this->tempFile, '<?php return ["initial" => true];');
        $path = str_replace('\\', '/', $this->tempFile);
        $input = $scheme . '://' . (str_starts_with($path, '/') ? '' : '/') . $path;

        self::assertTrue((new PhpFileConfig($input))->get('initial'));
    }
}
