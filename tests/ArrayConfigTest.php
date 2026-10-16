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

use stdClass;
use FastForward\Config\ArrayConfig;
use FastForward\Config\Exception\InvalidArgumentException;
use FastForward\Config\Helper\ConfigHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
    /**
     * @param string $operation
     */
    #[DataProvider('lookupOperations')]
    public function testRejectsEmptyLookupPaths(string $operation): void
    {
        $config = new ArrayConfig();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path cannot be an empty string');

        $config->{$operation}('');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function lookupOperations(): array
    {
        return [
            'get' => ['get'],
            'has' => ['has'],
            'remove' => ['remove'],
        ];
    }

    /**
     * @param string $key
     */
    #[DataProvider('configurationPaths')]
    public function testSupportsExistingPathDelimitersAndEmptySegments(string $key): void
    {
        $config = new ArrayConfig([
            $key => 'initial',
        ]);

        self::assertTrue($config->has($key));
        self::assertSame('initial', $config->get($key));

        $config->set($key, 'updated');
        self::assertSame('updated', $config[$key]);

        unset($config[$key]);
        self::assertFalse($config->has($key));
        self::assertSame('fallback', $config->get($key, 'fallback'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function configurationPaths(): array
    {
        return [
            'dot' => ['app.database.host'],
            'slash' => ['app/database/host'],
            'mixed delimiters' => ['app/database.host'],
            'empty segments' => ['.app..host.'],
            'slash empty segments' => ['/app//host/'],
            'numeric segments' => ['services.0.options.1'],
            'numeric string key' => ['0'],
        ];
    }

    /**
     * @param mixed $value
     */
    #[DataProvider('falsyValuesProvider')]
    public function testExistenceAndDefaultsPreserveFalsyValues(mixed $value): void
    {
        $config = new ArrayConfig([
            'app.value' => $value,
        ]);

        self::assertTrue($config->has('app.value'));
        self::assertTrue(isset($config['app.value']));
        self::assertSame($value ?? 'fallback', $config->get('app.value', 'fallback'));
        self::assertSame($value, $config['app.value']);
        self::assertFalse($config->has('app.value.child'));
        self::assertSame('fallback', $config->get('app.value.child', 'fallback'));
        self::assertSame([
            'app' => [
                'value' => $value,
            ],
        ], $config->toArray());

        $config->remove('app.value');
        self::assertFalse($config->has('app.value'));
        self::assertSame([
            'app' => [],
        ], $config->toArray());
    }

    /**
     * @return void
     */
    public function testMissingAndNullValuesWrapAssociativeDefaults(): void
    {
        $config = new ArrayConfig([
            'null' => null,
        ]);

        foreach (['missing', 'null', 'missing.child'] as $key) {
            $value = $config->get($key, [
                'app.name' => 'fallback',
            ]);

            self::assertInstanceOf(ArrayConfig::class, $value);
            self::assertSame([
                'app' => [
                    'name' => 'fallback',
                ],
            ], $value->toArray());
        }
    }

    /**
     * @return void
     */
    public function testWrapsOnlyEmptyAndStringKeyedArrays(): void
    {
        $config = new ArrayConfig([
            'empty' => [],
            'map' => [
                'key' => 'value',
            ],
            'list' => ['A', 'B'],
            'sparse' => [
                2 => 'A',
                5 => 'B',
            ],
            'mixed' => [
                0 => 'A',
                'key' => 'B',
            ],
        ]);

        self::assertInstanceOf(ArrayConfig::class, $config->get('empty'));
        self::assertSame([], $config->get('empty')->toArray());
        self::assertInstanceOf(ArrayConfig::class, $config->get('map'));
        self::assertSame(['A', 'B'], $config->get('list'));
        self::assertSame([
            2 => 'A',
            5 => 'B',
        ], $config->get('sparse'));
        self::assertSame([
            0 => 'A',
            'key' => 'B',
        ], $config->get('mixed'));
    }

    /**
     * @return void
     */
    public function testReadingAndRemovingMissingPathsDoNotMutateData(): void
    {
        $values = [
            'app' => [
                'scalar' => 'value',
                'null' => null,
                'list' => ['A', 'B'],
            ],
        ];
        $config = new ArrayConfig($values);

        foreach (['missing', 'missing.child', 'app.scalar.child', 'app.null.child', 'app.list.9'] as $key) {
            self::assertFalse($config->has($key));
            self::assertNull($config->get($key));
            $config->remove($key);
            self::assertSame($values, $config->toArray());
        }

        $config->remove('app.list.0');
        self::assertSame([
            1 => 'B',
        ], $config->get('app.list'));
    }

    /**
     * @return void
     */
    public function testNestedWritesReplaceScalarAncestorsAndMergeMaps(): void
    {
        $config = new ArrayConfig([
            'app' => 'old',
        ]);
        $config->set('app.database.host', 'localhost');
        $config->set([
            'app.database.port' => 3306,
            'app.debug' => false,
        ]);

        self::assertSame([
            'app' => [
                'database' => [
                    'host' => 'localhost',
                    'port' => 3306,
                ],
                'debug' => false,
            ],
        ], $config->toArray());

        $config->set('app.database');
        self::assertTrue($config->has('app.database'));
        self::assertSame('fallback', $config->get('app.database', 'fallback'));
        self::assertFalse($config->get('app.debug'));
    }

    /**
     * @return void
     */
    public function testSubtreesAndExportsAreIndependentArraySnapshots(): void
    {
        $config = new ArrayConfig([
            'app.name' => 'original',
        ]);
        $subtree = $config->get('app');
        $subtree->set('name', 'subtree');

        $export = $config->toArray();
        $export['app']['name'] = 'export';

        self::assertSame('original', $config->get('app.name'));
        self::assertSame('subtree', $subtree->get('name'));
    }

    /**
     * @return void
     */
    public function testIterationRetainsLeafTypesAndSkipsEmptyArrays(): void
    {
        $object = new stdClass();
        $config = new ArrayConfig([
            'app' => [
                'null' => null,
                'empty' => [],
                'list' => [false, $object],
            ],
        ]);

        self::assertSame([
            'app.null' => null,
            'app.list.0' => false,
            'app.list.1' => $object,
        ], iterator_to_array($config));
    }

    /**
     * @return void
     */
    #[Test]
    public function testGetWillReturnPrimitiveOrNestedConfig(): void
    {
        $key       = uniqid('key_');
        $nestedKey = $key . '.nested';
        $val       = uniqid('val_');
        $default   = uniqid('def_');

        $config = new ArrayConfig([
            $nestedKey => $val,
        ]);

        self::assertSame($val, $config->get($nestedKey));
        self::assertSame($default, $config->get(uniqid('missing_'), $default));

        $nested = $config->get($key);
        self::assertInstanceOf(ArrayConfig::class, $nested);
        self::assertSame([
            $key => [
                'nested' => $val,
            ],
        ], $config->toArray());
    }

    /**
     * @return void
     */
    #[Test]
    public function testHasReturnsExpectedResults(): void
    {
        $key    = uniqid('foo.') . 'bar';
        $config = new ArrayConfig([
            $key => 'value',
        ]);

        self::assertTrue($config->has($key));
        self::assertFalse($config->has(uniqid('nope_', true)));
    }

    /**
     * @return void
     */
    #[Test]
    public function testSetWithArrayMergesCorrectly(): void
    {
        $key1 = uniqid('x_');
        $key2 = uniqid('y_');
        $v1   = random_int(1, 100);
        $v2   = random_int(101, 200);

        $config = new ArrayConfig([
            $key1 => $v1,
        ]);
        $config->set([
            $key2 => $v2,
        ]);

        self::assertSame([
            $key1 => $v1,
            $key2 => $v2,
        ], $config->toArray());
    }

    /**
     * @return void
     */
    #[Test]
    public function testSetWithKeyValuePairs(): void
    {
        $key = uniqid('key_');
        $val = uniqid('val_');

        $config = new ArrayConfig();
        $config->set($key, $val);

        self::assertSame([
            $key => $val,
        ], $config->toArray());
    }

    /**
     * @param mixed $value
     *
     * @return void
     */
    #[Test]
    #[DataProvider('falsyValuesProvider')]
    public function testSetWithFalsyValues(mixed $value): void
    {
        $key    = 'test.' . uniqid('falsy_');
        $config = new ArrayConfig();
        $config->set($key, $value);

        self::assertSame($value, $config->get($key));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function falsyValuesProvider(): array
    {
        return [
            'boolean false' => [false],
            'integer zero'  => [0],
            'string zero'   => ['0'],
            'empty string'  => [''],
            'null value'    => [null],
        ];
    }

    /**
     * @return void
     */
    #[Test]
    public function testSetThrowsExceptionForInvalidKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $config = new ArrayConfig();
        $config->set([123], 'value');
    }

    /**
     * @return void
     */
    #[Test]
    public function testSetAcceptsAnotherConfigInterface(): void
    {
        $key   = uniqid('shared_');
        $value = random_int(100, 999);

        $source = new ArrayConfig([
            $key => $value,
        ]);

        $config = new ArrayConfig();
        $config->set($source);

        self::assertSame([
            $key => $value,
        ], $config->toArray());
    }

    /**
     * @return void
     */
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

    /**
     * @return void
     */
    #[Test]
    public function testDotNotationMergesAssociativeNestedKeys(): void
    {
        $config = new ArrayConfig([
            'db.connection.host' => 'localhost',
            'db.connection.port' => 3306,
            'db.options'         => [
                'charset' => 'utf8',
            ],
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

    /**
     * @return void
     */
    #[Test]
    public function testRemoveKeyRemovesTopLevelKeys(): void
    {
        $key = uniqid('key_');
        $val = uniqid('value_');

        $config = new ArrayConfig([
            $key => $val,
        ]);
        $config->remove($key);

        self::assertFalse($config->has($key));
        self::assertSame([], $config->toArray());
    }

    /**
     * @return void
     */
    #[Test]
    public function testRemoveKeyRemovesNestedKeys(): void
    {
        $key1 = uniqid('key1.');
        $key2 = $key1 . 'nested';
        $val  = uniqid('value_');

        $config = new ArrayConfig([
            $key2 => $val,
        ]);
        $config->remove($key2);

        self::assertFalse($config->has($key2));
        self::assertSame([
            'key1' => [],
        ], $config->toArray());
    }

    /**
     * @return void
     */
    #[Test]
    public function testRemoveKeyRemovesDeepNestedKeys(): void
    {
        $key1 = uniqid('key1.');
        $key2 = $key1 . '.nested.';
        $key3 = $key2 . 'deep';
        $val  = uniqid('value_');

        $config = new ArrayConfig([
            $key3 => $val,
        ]);
        $config->remove($key3);

        $config2 = new ArrayConfig([
            $key1 => [
                'nested' => [],
            ],
        ]);

        self::assertFalse($config->has($key3));
        self::assertSame($config2->toArray(), $config->toArray());
    }

    /**
     * @param string $form
     */
    #[Test]
    #[DataProvider('listAssignmentForms')]
    public function testExplicitListAssignmentRetainsIndexReplacement(string $form): void
    {
        $config = new ArrayConfig([
            'console' => [
                'commands' => ['A', 'B'],
            ],
        ]);

        match ($form) {
            'key' => $config->set('console.commands', ['C']),
            'array' => $config->set([
                'console' => [
                    'commands' => ['C'],
                ],
            ]),
            'object' => $config->set(new ArrayConfig([
                'console' => [
                    'commands' => ['C'],
                ],
            ])),
        };

        self::assertSame(['C', 'B'], $config->get('console.commands'));
        $config->set('console.commands', []);
        self::assertSame(['C', 'B'], $config->get('console.commands'));
        $config->set('console.commands');
        self::assertSame([
            'console' => [
                'commands' => null,
            ],
        ], $config->toArray());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function listAssignmentForms(): array
    {
        return [
            'key' => ['key'],
            'array' => ['array'],
            'object' => ['object'],
        ];
    }
}
