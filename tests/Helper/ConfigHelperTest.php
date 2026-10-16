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

namespace FastForward\Config\Tests\Helper;

use stdClass;
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
#[CoversClass(ConfigHelper::class)]
#[UsesClass(InvalidArgumentException::class)]
final class ConfigHelperTest extends TestCase
{
    /**
     * @return void
     */
    public function testMergeRecursesThroughMapsAndConcatenatesOnlySequentialLists(): void
    {
        self::assertSame([
            'commands' => ['A', 'B', 'B'],
            'options' => [
                'name' => 'last',
                'keep' => true,
            ],
            'extra' => null,
        ], ConfigHelper::merge([
            'commands' => ['A', 'B'],
            'options' => [
                'name' => 'first',
                'keep' => true,
            ],
        ], [
            'commands' => ['B'],
            'options' => [
                'name' => 'last',
            ],
            'extra' => null,
        ]));
    }

    /**
     * @return void
     */
    public function testPathRejectsAnEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConfigHelper::path('');
    }

    /**
     * @param mixed $value
     * @param bool $expected
     */
    #[DataProvider('arrayShapes')]
    public function testAssociativeDetectionPreservesStringKeyOnlySemantics(mixed $value, bool $expected): void
    {
        self::assertSame($expected, ConfigHelper::isAssoc($value));
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function arrayShapes(): array
    {
        return [
            'empty' => [[], true],
            'map' => [[
                'key' => 'value',
            ], true],
            'numeric-looking string' => [[
                '01' => 'value',
            ], true],
            'list' => [['A', 'B'], false],
            'sparse' => [[
                2 => 'A',
                5 => 'B',
            ], false],
            'mixed' => [[
                0 => 'A',
                'key' => 'B',
            ], false],
            'numeric string' => [[
                '0' => 'A',
            ], false],
            'object' => [new stdClass(), false],
            'null' => [null, false],
        ];
    }

    /**
     * @return void
     */
    public function testNormalizationPreservesListsSparseAndMixedArrays(): void
    {
        foreach ([
            [[
                'app.name' => 'inside a list',
            ]],
            [
                2 => [
                    'app.name' => 'inside a sparse array',
                ],
            ],
            [
                0 => 'A',
                'app.name' => 'inside a mixed array',
            ],
        ] as $input) {
            self::assertSame($input, ConfigHelper::normalize($input));
            self::assertSame([
                'items' => $input,
            ], ConfigHelper::normalize([
                'items' => $input,
            ]));
        }
    }

    /**
     * @return void
     */
    public function testNormalizationSupportsSlashAndConcatenatesOverlappingLists(): void
    {
        self::assertSame([
            'app' => [
                'commands' => ['A', 'B', 'A'],
                'name' => 'example',
            ],
        ], ConfigHelper::normalize([
            'app' => [
                'commands' => ['A'],
            ],
            'app/commands' => ['B', 'A'],
            'app/name' => 'example',
        ]));
    }

    /**
     * @return void
     */
    public function testNormalizationRetainsInputOrderPrecedence(): void
    {
        self::assertSame([
            'app' => [
                'name' => 'last',
            ],
        ], ConfigHelper::normalize([
            'app.debug' => true,
            'app' => [
                'name' => 'last',
            ],
        ]));
        self::assertSame([
            'app' => [
                'name' => 'last',
            ],
        ], ConfigHelper::normalize([
            'app' => 'first',
            'app/name' => 'last',
        ]));
    }

    /**
     * @return void
     */
    #[Test]
    public function testIsAssocWillReturnTrueForAssociativeArray(): void
    {
        $array = [
            'key' => 'value',
        ];

        self::assertTrue(ConfigHelper::isAssoc($array));
    }

    /**
     * @return void
     */
    #[Test]
    public function testIsAssocWillReturnFalseForSequentialArray(): void
    {
        $array = ['value1', 'value2'];

        self::assertFalse(ConfigHelper::isAssoc($array));
    }

    /**
     * @return void
     */
    #[Test]
    public function testIsAssocWillReturnFalseForNonArray(): void
    {
        self::assertFalse(ConfigHelper::isAssoc('string'));
        self::assertFalse(ConfigHelper::isAssoc(123));
        self::assertFalse(ConfigHelper::isAssoc(null));
        self::assertFalse(ConfigHelper::isAssoc(true));
    }

    /**
     * @return void
     */
    #[Test]
    public function testNormalizeWillConvertDotNotationToNestedArray(): void
    {
        $input = [
            'database.host' => 'localhost',
            'database.port' => 3306,
            'app.name'      => 'TestApp',
        ];

        $expected = [
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
            ],
            'app' => [
                'name' => 'TestApp',
            ],
        ];

        $result = ConfigHelper::normalize($input);

        self::assertEquals($expected, $result);
    }

    /**
     * @return void
     */
    #[Test]
    public function testNormalizeWillHandleMixedNestedArrays(): void
    {
        $input = [
            'database.host'   => 'localhost',
            'database.config' => [
                'port' => 3306,
            ],
        ];

        $expected = [
            'database' => [
                'host'   => 'localhost',
                'config' => [
                    'port' => 3306,
                ],
            ],
        ];

        $result = ConfigHelper::normalize($input);

        self::assertEquals($expected, $result);
    }

    /**
     * @return void
     */
    #[Test]
    public function testNormalizeWillReturnIndexedArrayUnchanged(): void
    {
        $input = ['foo', 'bar'];

        $result = ConfigHelper::normalize($input);

        self::assertEquals($input, $result);
    }

    /**
     * @return void
     */
    #[Test]
    public function testNormalizeWillMergeArraysWhenKeysOverlap(): void
    {
        $input = [
            'settings'         => [
                'display' => [
                    'resolution' => '1080p',
                ],
            ],
            'settings.display' => [
                'theme' => 'dark',
            ],
        ];

        $expected = [
            'settings' => [
                'display' => [
                    'theme'      => 'dark',
                    'resolution' => '1080p',
                ],
            ],
        ];

        $result = ConfigHelper::normalize($input);

        self::assertEquals($expected, $result);
    }

    /**
     * @return void
     */
    #[Test]
    public function testFlattenWillConvertNestedArrayToDotNotation(): void
    {
        $input = [
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
            ],
            'app' => [
                'name' => 'TestApp',
            ],
        ];

        $expected = [
            'database.host' => 'localhost',
            'database.port' => 3306,
            'app.name'      => 'TestApp',
        ];

        $result = iterator_to_array(ConfigHelper::flatten($input));

        self::assertEquals($expected, $result);
    }

    /**
     * @return void
     */
    #[Test]
    public function testFlattenWillHandleEmptyArray(): void
    {
        $input = [];

        $result = iterator_to_array(ConfigHelper::flatten($input));

        self::assertEquals([], $result);
    }
}
