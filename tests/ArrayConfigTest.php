<?php

declare(strict_types=1);

namespace FastForward\Config\Tests;

use FastForward\Config\ArrayConfig;
use FastForward\Config\ConfigInterface;
use FastForward\Config\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayConfig::class)]
#[UsesClass(InvalidArgumentException::class)]
final class ArrayConfigTest extends TestCase
{
    #[Test]
    public function testGetWillReturnPrimitiveOrNestedConfig()
    {
        $key = uniqid('key_');
        $nestedKey = $key . '.nested';
        $val = uniqid('val_');
        $default = uniqid('def_');

        $config = new ArrayConfig([$nestedKey => $val]);

        $this->assertSame($val, $config->get($nestedKey));
        $this->assertSame($default, $config->get(uniqid('missing_'), $default));

        $nested = $config->get($key);
        $this->assertInstanceOf(ArrayConfig::class, $nested);
        $this->assertSame([$key => ['nested' => $val]], $config->toArray());
    }

    #[Test]
    public function testHasReturnsExpectedResults()
    {
        $key = uniqid('foo.') . 'bar';
        $config = new ArrayConfig([$key => 'value']);

        $this->assertTrue($config->has($key));
        $this->assertFalse($config->has(uniqid('nope_', true)));
    }

    #[Test]
    public function testSetWithArrayMergesCorrectly()
    {
        $key1 = uniqid('x_');
        $key2 = uniqid('y_');
        $v1 = mt_rand(1, 100);
        $v2 = mt_rand(101, 200);

        $config = new ArrayConfig([$key1 => $v1]);
        $config->set([$key2 => $v2]);

        $this->assertSame([$key1 => $v1, $key2 => $v2], $config->toArray());
    }

    #[Test]
    public function testSetWithKeyValuePairs()
    {
        $key = uniqid('key_');
        $val = uniqid('val_');

        $config = new ArrayConfig();
        $config->set($key, $val);

        $this->assertSame([$key => $val], $config->toArray());
    }

    #[Test]
    public function testSetThrowsExceptionForInvalidKey()
    {
        $this->expectException(InvalidArgumentException::class);

        $config = new ArrayConfig();
        $config->set([123], 'value');
    }

    #[Test]
    public function testSetAcceptsAnotherConfigInterface()
    {
        $key = uniqid('shared_');
        $value = mt_rand(100, 999);

        $source = new ArrayConfig([$key => $value]);

        $config = new ArrayConfig();
        $config->set($source);

        $this->assertSame([$key => $value], $config->toArray());
    }

    #[Test]
    public function testGetIteratorYieldsAllKeys()
    {
        $data = [
            uniqid('k1_') => mt_rand(1, 10),
            uniqid('k2_') => mt_rand(11, 20),
        ];

        $config = new ArrayConfig($data);

        $this->assertSame($data, iterator_to_array($config));
    }

    #[Test]
    public function testDotNotationMergesAssociativeNestedKeys()
    {
        $config = new ArrayConfig([
            'db.connection.host' => 'localhost',
            'db.connection.port' => 3306,
            'db.options' => ['charset' => 'utf8'],
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

        $this->assertSame($expected, $config->toArray());
    }
}
