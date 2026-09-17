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
use PHPUnit\Framework\Attributes\CoversClass;
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
final class PhpFileConfigTest extends TestCase
{
    private string $tempFile;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . uniqid('config_', true) . '.php';
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    /**
     * @return void
     */
    #[Test]
    public function testInvokeWillReturnArrayConfigFromFile(): void
    {
        $data = [
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
            ],
            'debug' => true,
        ];

        file_put_contents($this->tempFile, '<?php return ' . var_export($data, true) . ';');

        $config = new PhpFileConfig($this->tempFile);

        self::assertSame($data, $config->toArray());
        self::assertSame('localhost', $config->get('database.host'));
        self::assertTrue($config->has('database.port'));
    }

    /**
     * @return void
     */
    #[Test]
    public function testInvokeWillThrowExceptionWhenFileDoesNotExistAndNoDefault(): void
    {
        $config = new PhpFileConfig($this->tempFile);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The file "%s" does not exist or is not readable.', $this->tempFile));

        $config->toArray();
    }

    /**
     * @return void
     */
    #[Test]
    public function testInvokeWillUseDefaultConfigWhenFileDoesNotExist(): void
    {
        $defaultData = [
            'app' => [
                'name' => 'MyApp',
            ],
        ];

        $config = new PhpFileConfig(
            file: $this->tempFile,
            persistent: false,
            defaultConfig: new ArrayConfig($defaultData),
        );

        self::assertSame($defaultData, $config->toArray());
        self::assertFileDoesNotExist($this->tempFile);
    }

    /**
     * @return void
     */
    #[Test]
    public function testInvokeWillNotPersistDefaultConfigOnReadEvenWhenPersistentIsTrue(): void
    {
        $defaultData = [
            'app' => [
                'name' => 'PersistedApp',
            ],
        ];

        $config = new PhpFileConfig(
            file: $this->tempFile,
            persistent: true,
            defaultConfig: $defaultData,
        );

        self::assertSame($defaultData, $config->toArray());
        self::assertFileDoesNotExist($this->tempFile);
    }

    /**
     * @return void
     */
    #[Test]
    public function testSetWillPersistDefaultConfigAndMutationsWhenPersistentIsTrue(): void
    {
        $defaultData = [
            'app' => [
                'name' => 'PersistedApp',
            ],
        ];

        $config = new PhpFileConfig(
            file: $this->tempFile,
            persistent: true,
            defaultConfig: $defaultData,
        );

        $config->set('app.env', 'testing');

        self::assertFileExists($this->tempFile);

        $reloaded = new PhpFileConfig($this->tempFile);
        self::assertSame([
            'app' => [
                'name' => 'PersistedApp',
                'env'  => 'testing',
            ],
        ], $reloaded->toArray());
    }

    /**
     * @return void
     */
    #[Test]
    public function testSetWillCreateDirectoryIfItDoesNotExistWhenPersistentIsTrue(): void
    {
        $baseDir  = sys_get_temp_dir() . '/base_' . uniqid();
        $subDir   = $baseDir . '/nested/sub/dir';
        $filePath = $subDir . '/config.php';

        try {
            $config = new PhpFileConfig(
                file: $filePath,
                persistent: true,
                defaultConfig: ['initial' => true],
            );

            self::assertDirectoryDoesNotExist($subDir);

            $config->set('new_key', 'new_val');

            self::assertDirectoryExists($subDir);
            self::assertFileExists($filePath);

            $reloaded = new PhpFileConfig($filePath);
            self::assertSame([
                'initial' => true,
                'new_key' => 'new_val',
            ], $reloaded->toArray());
        } finally {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            if (is_dir($subDir)) {
                rmdir($subDir);
                rmdir($baseDir . '/nested/sub');
                rmdir($baseDir . '/nested');
                rmdir($baseDir);
            }
        }
    }

    /**
     * @return void
     */
    #[Test]
    public function testInvokeWillThrowExceptionWhenFileDoesNotReturnArray(): void
    {
        file_put_contents($this->tempFile, '<?php return "invalid";');

        $config = new PhpFileConfig($this->tempFile);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The configuration file "%s" must return an array.', $this->tempFile));

        $config->toArray();
    }

    /**
     * @return void
     */
    #[Test]
    public function testSetWillNotUpdateFileWhenPersistentIsFalse(): void
    {
        $initialData = [
            'key' => 'initial_value',
        ];

        file_put_contents($this->tempFile, '<?php return ' . var_export($initialData, true) . ';');

        $config = new PhpFileConfig($this->tempFile, persistent: false);
        $config->set('key', 'new_value');

        self::assertSame('new_value', $config->get('key'));

        $reloaded = new PhpFileConfig($this->tempFile);
        self::assertSame('initial_value', $reloaded->get('key'));
    }

    /**
     * @return void
     */
    #[Test]
    public function testSetWillUpdateFileWhenPersistentIsTrue(): void
    {
        $initialData = [
            'key' => 'initial_value',
        ];

        file_put_contents($this->tempFile, '<?php return ' . var_export($initialData, true) . ';');

        $config = new PhpFileConfig($this->tempFile, persistent: true);
        $config->set('key', 'new_value');
        $config->set('nested.setting', 42);

        self::assertSame('new_value', $config->get('key'));
        self::assertSame(42, $config->get('nested.setting'));

        $reloaded = new PhpFileConfig($this->tempFile);
        self::assertSame('new_value', $reloaded->get('key'));
        self::assertSame(42, $reloaded->get('nested.setting'));
    }

    /**
     * @return void
     */
    #[Test]
    public function testRemoveWillNotUpdateFileWhenPersistentIsFalse(): void
    {
        $initialData = [
            'key' => 'value',
        ];

        file_put_contents($this->tempFile, '<?php return ' . var_export($initialData, true) . ';');

        $config = new PhpFileConfig($this->tempFile, persistent: false);
        $config->remove('key');

        self::assertFalse($config->has('key'));

        $reloaded = new PhpFileConfig($this->tempFile);
        self::assertTrue($reloaded->has('key'));
    }

    /**
     * @return void
     */
    #[Test]
    public function testRemoveWillUpdateFileWhenPersistentIsTrue(): void
    {
        $initialData = [
            'key' => 'value',
            'keep' => 'preserved',
        ];

        file_put_contents($this->tempFile, '<?php return ' . var_export($initialData, true) . ';');

        $config = new PhpFileConfig($this->tempFile, persistent: true);
        $config->remove('key');

        self::assertFalse($config->has('key'));
        self::assertTrue($config->has('keep'));

        $reloaded = new PhpFileConfig($this->tempFile);
        self::assertFalse($reloaded->has('key'));
        self::assertTrue($reloaded->has('keep'));
    }

    /**
     * @return void
     */
    #[Test]
    public function testArrayAccessWithPersistence(): void
    {
        $initialData = [
            'key' => 'value',
        ];

        file_put_contents($this->tempFile, '<?php return ' . var_export($initialData, true) . ';');

        $config = new PhpFileConfig($this->tempFile, persistent: true);

        self::assertTrue(isset($config['key']));
        self::assertSame('value', $config['key']);

        $config['new_key'] = 'persisted_value';
        unset($config['key']);

        self::assertFalse(isset($config['key']));
        self::assertSame('persisted_value', $config['new_key']);

        $reloaded = new PhpFileConfig($this->tempFile);
        self::assertFalse($reloaded->has('key'));
        self::assertSame('persisted_value', $reloaded->get('new_key'));
    }

    /**
     * @return void
     */
    #[Test]
    public function testIterationReturnsFlattenedKeys(): void
    {
        $data = [
            'db' => [
                'host' => '127.0.0.1',
            ],
        ];

        file_put_contents($this->tempFile, '<?php return ' . var_export($data, true) . ';');

        $config = new PhpFileConfig($this->tempFile);
        $iterated = iterator_to_array($config);

        self::assertSame(['db.host' => '127.0.0.1'], $iterated);
    }

    /**
     * @return void
     */
    #[Test]
    public function testInvokeWillThrowExceptionWhenPathIsDirectory(): void
    {
        $dir = sys_get_temp_dir() . '/config_dir_' . uniqid();
        mkdir($dir);

        try {
            $config = new PhpFileConfig($dir);
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage(\sprintf('The file "%s" does not exist or is not readable.', $dir));
            $config->toArray();
        } finally {
            rmdir($dir);
        }
    }

    /**
     * @return void
     */
    #[Test]
    public function testDumpWillThrowExceptionWhenDirectoryIsNotWritable(): void
    {
        $unwritableDir = '/proc/nonexistent_' . uniqid() . '/file.php';
        $config = new PhpFileConfig($unwritableDir, persistent: true, defaultConfig: ['foo' => 'bar']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The file "%s" is not writable.', $unwritableDir));

        $config->set('foo', 'baz');
    }

    /**
     * @return void
     */
    #[Test]
    public function testDumpWillThrowExceptionWhenFileIsNotWritable(): void
    {
        file_put_contents($this->tempFile, '<?php return ["foo" => "bar"];');
        chmod($this->tempFile, 0444);

        try {
            $config = new PhpFileConfig($this->tempFile, persistent: true);
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage(\sprintf('The file "%s" is not writable.', $this->tempFile));

            $config->set('foo', 'baz');
        } finally {
            chmod($this->tempFile, 0644);
        }
    }

    /**
     * @return void
     */
    #[Test]
    public function testDumpWillThrowExceptionWhenValueCannotBeExported(): void
    {
        $config = new PhpFileConfig($this->tempFile, persistent: true, defaultConfig: []);

        $resource = fopen('php://memory', 'r');
        $this->expectException(InvalidArgumentException::class);

        try {
            $config->set('resource', $resource);
        } finally {
            if (\is_resource($resource)) {
                fclose($resource);
            }
        }
    }
}

