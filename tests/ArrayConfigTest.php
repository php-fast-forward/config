<?php

declare(strict_types=1);

/**
 * This file is part of php-fast-forward/config.
 *
 * This source file is subject to the license bundled
 * with this source code in the file LICENSE.
 *
 * @link      https://github.com/php-fast-forward/config
 * @copyright Copyright (c) 2025 Felipe Sayão Lobato Abreu <github@mentordosnerds.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace FastForward\Config\Tests;

use FastForward\Config\ArrayConfig;
use FastForward\Config\Exception\InvalidArgumentException;
use FastForward\Config\Helper\ConfigHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ArrayConfig::class)]
#[UsesClass(ConfigHelper::class)]
#[UsesClass(InvalidArgumentException::class)]
final class ArrayConfigTest extends TestCase
{
    #[Test]
    public function testGetWillReturnPrimitiveOrNestedConfig(): void
    {
        $key       = uniqid('key_');
        $nestedKey = $key . '.nested';
        $val       = uniqid('val_');
        $default   = uniqid('def_');

        $config = new ArrayConfig([$nestedKey => $val]);

        self::assertSame($val, $config->get($nestedKey));
        self::assertSame($default, $config->get(uniqid('missing_'), $default));

        $nested = $config->get($key);
        self::assertInstanceOf(ArrayConfig::class, $nested);
        self::assertSame([$key => ['nested' => $val]], $config->toArray());
    }

    #[Test]
    public function testHasReturnsExpectedResults(): void
    {
        $key    = uniqid('foo.') . 'bar';
        $config = new ArrayConfig([$key => 'value']);

        self::assertTrue($config->has($key));
        self::assertFalse($config->has(uniqid('nope_', true)));
    }

    #[Test]
    public function testSetWithArrayMergesCorrectly(): void
    {
        $key1 = uniqid('x_');
        $key2 = uniqid('y_');
        $v1   = random_int(1, 100);
        $v2   = random_int(101, 200);

        $config = new ArrayConfig([$key1 => $v1]);
        $config->set([$key2 => $v2]);

        self::assertSame([$key1 => $v1, $key2 => $v2], $config->toArray());
    }

    #[Test]
    public function testSetWithKeyValuePairs(): void
    {
        $key = uniqid('key_');
        $val = uniqid('val_');

        $config = new ArrayConfig();
        $config->set($key, $val);

        self::assertSame([$key => $val], $config->toArray());
    }

    #[Test]
    public function testSetThrowsExceptionForInvalidKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $config = new ArrayConfig();
        $config->set([123], 'value');
    }

    #[Test]
    public function testSetAcceptsAnotherConfigInterface(): void
    {
        $key   = uniqid('shared_');
        $value = random_int(100, 999);

        $source = new ArrayConfig([$key => $value]);

        $config = new ArrayConfig();
        $config->set($source);

        self::assertSame([$key => $value], $config->toArray());
    }

    #[Test]
    public function testGetIteratorYieldsAllKeys(): void
    {
        $data = [
            uniqid('k1_') => random_int(1, 10),
            uniqid('k2_') => random_int(11, 20),
        ];

        $config = new ArrayConfig($data);

        self::assertSame($data, iterator_to_array($config));
    }

    #[Test]
    public function testDotNotationMergesAssociativeNestedKeys(): void
    {
        $config = new ArrayConfig([
            'db.connection.host' => 'localhost',
            'db.connection.port' => 3306,
            'db.options'         => ['charset' => 'utf8'],
        ]);

        $expected = [
            'db' => [
                'connection' => [
                    'host' => 'localhost',
                    'port' => 3306,
                ],
                'options' => [
                    'charset' => 'utf8',
                ],
            ],
        ];

        self::assertSame($expected, $config->toArray());
    }
}
