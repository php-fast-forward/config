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

use Traversable;
use ArrayIterator;
use FastForward\Config\ArrayAccessConfigTrait;
use FastForward\Config\ConfigInterface;
use FastForward\Config\LazyLoadConfigTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @internal
 */
#[CoversTrait(LazyLoadConfigTrait::class)]
final class LazyLoadConfigTraitTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @return void
     */
    #[Test]
    public function testGetDelegatesToConfig(): void
    {
        $key     = uniqid('key_', true);
        $value   = uniqid('value_', true);
        $default = uniqid('default_', true);

        $fake = $this->createTestInstance([
            $key => $value,
        ]);

        self::assertSame($value, $fake->get($key));
        self::assertSame($default, $fake->get(uniqid('missing_', true), $default));
    }

    /**
     * @return void
     */
    #[Test]
    public function testRemoveRemovesFromConfig(): void
    {
        $key1 = uniqid('key1_', true);
        $key2 = uniqid('key2_', true);
        $val1 = random_int(1, 100);
        $val2 = random_int(101, 200);

        $fake = $this->createTestInstance([
            $key1 => $val1,
            $key2 => $val2,
        ]);

        self::assertTrue($fake->has($key1));
        self::assertTrue($fake->has($key2));

        $fake->remove($key1);

        self::assertFalse($fake->has($key1));
        self::assertTrue($fake->has($key2));
    }

    /**
     * @return void
     */
    #[Test]
    public function testHasReturnsExpectedResults(): void
    {
        $present = uniqid('present_', true);
        $absent  = uniqid('absent_', true);

        $fake = $this->createTestInstance([
            $present => random_int(1, 100),
        ]);

        self::assertTrue($fake->has($present));
        self::assertFalse($fake->has($absent));
    }

    /**
     * @return void
     */
    #[Test]
    public function testSetMergesValuesCorrectly(): void
    {
        $key1 = uniqid('x_', true);
        $key2 = uniqid('y_', true);
        $key3 = uniqid('z_', true);
        $val1 = random_int(100, 200);
        $val2 = random_int(201, 300);
        $val3 = random_int(301, 400);

        $fake = $this->createTestInstance([
            $key1 => $val1,
        ]);

        $fake->set([
            $key2 => $val2,
        ]);

        self::assertSame([
            $key1 => $val1,
            $key2 => $val2,
        ], $fake->toArray());

        $fake->set($key3, $val3);

        self::assertSame([
            $key1 => $val1,
            $key2 => $val2,
            $key3 => $val3,
        ], $fake->toArray());
    }

    /**
     * @return void
     */
    #[Test]
    public function testToArrayAndIteratorAreConsistent(): void
    {
        $a  = uniqid('a_', true);
        $b  = uniqid('b_', true);
        $v1 = random_int(1, 50);
        $v2 = random_int(51, 100);

        $fake = $this->createTestInstance([
            $a => $v1,
            $b => $v2,
        ]);

        self::assertSame([
            $a => $v1,
            $b => $v2,
        ], $fake->toArray());

        $collected = [];
        foreach ($fake->getIterator() as $k => $v) {
            $collected[$k] = $v;
        }

        self::assertSame($fake->toArray(), $collected);
    }

    /**
     * @param array $data
     *
     * @return ConfigInterface
     */
    private function createTestInstance(array $data): ConfigInterface
    {
        return new class ($data) implements ConfigInterface {
            use LazyLoadConfigTrait;

            /**
             * @param array $data
             */
            public function __construct(
                private array $data
            ) {}

            /**
             * @return ConfigInterface
             */
            public function __invoke(): ConfigInterface
            {
                return new class ($this->data) implements ConfigInterface {
                    use ArrayAccessConfigTrait;

                    /**
                     * @param array $items
                     */
                    public function __construct(
                        private array $items
                    ) {}

                    /**
                     * @param string $key
                     * @param mixed $default
                     *
                     * @return mixed
                     */
                    public function get(string $key, mixed $default = null): mixed
                    {
                        return $this->items[$key] ?? $default;
                    }

                    /**
                     * @param string $key
                     *
                     * @return bool
                     */
                    public function has(string $key): bool
                    {
                        return \array_key_exists($key, $this->items);
                    }

                    /**
                     * @param array|ConfigInterface|string $key
                     * @param mixed $value
                     *
                     * @return void
                     */
                    public function set(array|ConfigInterface|string $key, mixed $value = null): void
                    {
                        if (\is_string($key)) {
                            $this->items[$key] = $value;
                        } elseif ($key instanceof ConfigInterface) {
                            $this->items = [...$this->items, ...$key->toArray()];
                        } else {
                            $this->items = [...$this->items, ...$key];
                        }
                    }

                    /**
                     * @param string $key
                     *
                     * @return void
                     */
                    public function remove(string $key): void
                    {
                        if ($this->has($key)) {
                            unset($this->items[$key]);
                        }
                    }

                    /**
                     * @return array
                     */
                    public function toArray(): array
                    {
                        return $this->items;
                    }

                    /**
                     * @return Traversable
                     */
                    public function getIterator(): Traversable
                    {
                        return new ArrayIterator($this->items);
                    }
                };
            }
        };
    }
}
