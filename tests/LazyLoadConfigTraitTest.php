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

use FastForward\Config\ConfigInterface;
use FastForward\Config\LazyLoadConfigTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @internal
 */
#[CoversClass(LazyLoadConfigTrait::class)]
final class LazyLoadConfigTraitTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function testGetDelegatesToConfig(): void
    {
        $key     = uniqid('key_', true);
        $value   = uniqid('value_', true);
        $default = uniqid('default_', true);

        $fake = $this->createTestInstance([$key => $value]);

        self::assertSame($value, $fake->get($key));
        self::assertSame($default, $fake->get(uniqid('missing_', true), $default));
    }

    #[Test]
    public function testHasReturnsExpectedResults(): void
    {
        $present = uniqid('present_', true);
        $absent  = uniqid('absent_', true);

        $fake = $this->createTestInstance([$present => random_int(1, 100)]);

        self::assertTrue($fake->has($present));
        self::assertFalse($fake->has($absent));
    }

    #[Test]
    public function testSetMergesValuesCorrectly(): void
    {
        $key1 = uniqid('x_', true);
        $key2 = uniqid('y_', true);
        $key3 = uniqid('z_', true);
        $val1 = random_int(100, 200);
        $val2 = random_int(201, 300);
        $val3 = random_int(301, 400);

        $fake = $this->createTestInstance([$key1 => $val1]);

        $fake->set([$key2 => $val2]);

        self::assertSame([$key1 => $val1, $key2 => $val2], $fake->toArray());

        $fake->set($key3, $val3);

        self::assertSame([$key1 => $val1, $key2 => $val2, $key3 => $val3], $fake->toArray());
    }

    #[Test]
    public function testToArrayAndIteratorAreConsistent(): void
    {
        $a  = uniqid('a_', true);
        $b  = uniqid('b_', true);
        $v1 = random_int(1, 50);
        $v2 = random_int(51, 100);

        $fake = $this->createTestInstance([$a => $v1, $b => $v2]);

        self::assertSame([$a => $v1, $b => $v2], $fake->toArray());

        $collected = [];
        foreach ($fake->getIterator() as $k => $v) {
            $collected[$k] = $v;
        }

        self::assertSame($fake->toArray(), $collected);
    }

    private function createTestInstance(array $data): ConfigInterface
    {
        return new class($data) implements ConfigInterface {
            use LazyLoadConfigTrait;

            public function __construct(private array $data) {}

            public function __invoke(): ConfigInterface
            {
                return new class($this->data) implements ConfigInterface {
                    public function __construct(private array $items) {}

                    public function get(string $key, mixed $default = null): mixed
                    {
                        return $this->items[$key] ?? $default;
                    }

                    public function has(string $key): bool
                    {
                        return \array_key_exists($key, $this->items);
                    }

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

                    public function toArray(): array
                    {
                        return $this->items;
                    }

                    public function getIterator(): \Traversable
                    {
                        return new \ArrayIterator($this->items);
                    }
                };
            }
        };
    }
}
