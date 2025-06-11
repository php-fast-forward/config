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

namespace FastForward\Config\Tests\Helper;

use FastForward\Config\Helper\ConfigHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ConfigHelper::class)]
final class ConfigHelperTest extends TestCase
{
    #[Test]
    public function testIsAssocWillReturnTrueForAssociativeArray(): void
    {
        $array = ['key' => 'value'];

        self::assertTrue(ConfigHelper::isAssoc($array));
    }

    #[Test]
    public function testIsAssocWillReturnFalseForSequentialArray(): void
    {
        $array = ['value1', 'value2'];

        self::assertFalse(ConfigHelper::isAssoc($array));
    }

    #[Test]
    public function testIsAssocWillReturnFalseForNonArray(): void
    {
        self::assertFalse(ConfigHelper::isAssoc('string'));
        self::assertFalse(ConfigHelper::isAssoc(123));
        self::assertFalse(ConfigHelper::isAssoc(null));
        self::assertFalse(ConfigHelper::isAssoc(true));
    }

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

        self::assertSame($expected, $result);
    }

    #[Test]
    public function testNormalizeWillHandleMixedNestedArrays(): void
    {
        $input = [
            'database.host'   => 'localhost',
            'database.config' => ['port' => 3306],
        ];

        $expected = [
            'database' => [
                'host'   => 'localhost',
                'config' => ['port' => 3306],
            ],
        ];

        $result = ConfigHelper::normalize($input);

        self::assertSame($expected, $result);
    }

    #[Test]
    public function testNormalizeWillReturnIndexedArrayUnchanged(): void
    {
        $input = ['foo', 'bar'];

        $result = ConfigHelper::normalize($input);

        self::assertSame($input, $result);
    }

    #[Test]
    public function testNormalizeWillMergeArraysWhenKeysOverlap(): void
    {
        $input = [
            'settings'         => ['display' => ['resolution' => '1080p']],
            'settings.display' => ['theme' => 'dark'],
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

        self::assertSame($expected, $result);
    }

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

        self::assertSame($expected, $result);
    }

    #[Test]
    public function testFlattenWillHandleEmptyArray(): void
    {
        $input = [];

        $result = iterator_to_array(ConfigHelper::flatten($input));

        self::assertSame([], $result);
    }
}
