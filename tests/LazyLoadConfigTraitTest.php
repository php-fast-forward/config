<?php

declare(strict_types=1);

namespace FastForward\Config\Tests;

use FastForward\Config\ConfigInterface;
use FastForward\Config\LazyLoadConfigTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

#[CoversClass(LazyLoadConfigTrait::class)]
final class LazyLoadConfigTraitTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function testGetDelegatesToConfig()
    {
        $key = uniqid('key_', true);
        $value = uniqid('value_', true);
        $default = uniqid('default_', true);

        $fake = $this->createTestInstance([$key => $value]);

        $this->assertSame($value, $fake->get($key));
        $this->assertSame($default, $fake->get(uniqid('missing_', true), $default));
    }

    #[Test]
    public function testHasReturnsExpectedResults()
    {
        $present = uniqid('present_', true);
        $absent = uniqid('absent_', true);

        $fake = $this->createTestInstance([$present => mt_rand(1, 100)]);

        $this->assertTrue($fake->has($present));
        $this->assertFalse($fake->has($absent));
    }

    #[Test]
    public function testSetMergesValuesCorrectly()
    {
        $key1 = uniqid('x_', true);
        $key2 = uniqid('y_', true);
        $key3 = uniqid('z_', true);
        $val1 = mt_rand(100, 200);
        $val2 = mt_rand(201, 300);
        $val3 = mt_rand(301, 400);

        $fake = $this->createTestInstance([$key1 => $val1]);

        $fake->set([$key2 => $val2]);

        $this->assertSame([$key1 => $val1, $key2 => $val2], $fake->toArray());

        $fake->set($key3, $val3);

        $this->assertSame([$key1 => $val1, $key2 => $val2, $key3 => $val3], $fake->toArray());
    }

    #[Test]
    public function testToArrayAndIteratorAreConsistent()
    {
        $a = uniqid('a_', true);
        $b = uniqid('b_', true);
        $v1 = mt_rand(1, 50);
        $v2 = mt_rand(51, 100);

        $fake = $this->createTestInstance([$a => $v1, $b => $v2]);

        $this->assertSame([$a => $v1, $b => $v2], $fake->toArray());

        $collected = [];
        foreach ($fake->getIterator() as $k => $v) {
            $collected[$k] = $v;
        }

        $this->assertSame($fake->toArray(), $collected);
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
                        return array_key_exists($key, $this->items);
                    }

                    public function set(array|string|ConfigInterface $key, mixed $value = null): void
                    {
                        if (is_string($key)) {
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
