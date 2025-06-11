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

use FastForward\Config\ArrayAccessConfigTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @internal
 */
#[CoversTrait(ArrayAccessConfigTrait::class)]
final class ArrayAccessConfigTraitTest extends TestCase
{
    use ProphecyTrait;

    public function testOffsetExistsWillCallHasMethod(): void
    {
        $object = $this->createTraitInstance(has: true);
        self::assertTrue($object->offsetExists('key'));

        $object = $this->createTraitInstance(has: false);
        self::assertFalse($object->offsetExists('key'));
    }

    public function testOffsetGetWillCallGetMethod(): void
    {
        $object = $this->createTraitInstance(get: 'foo');
        self::assertSame('foo', $object->offsetGet('bar'));
    }

    public function testOffsetSetWillCallSetMethod(): void
    {
        $object = $this->createTraitInstance();
        $object->offsetSet('alpha', 'beta');

        self::assertSame(['alpha' => 'beta'], $object->getSetCalls());
    }

    public function testOffsetUnsetWillCallRemoveMethod(): void
    {
        $object = $this->createTraitInstance();
        $object->offsetUnset('delta');

        self::assertSame(['delta'], $object->getRemoveCalls());
    }

    private function createTraitInstance(bool $has = false, mixed $get = null): ArrayAccess
    {
        return new class($has, $get) implements ArrayAccess {
            use ArrayAccessConfigTrait;

            private array $setCalls = [];

            private array $removeCalls = [];

            private bool $hasReturn;

            private mixed $getReturn;

            public function __construct(bool $has, mixed $get)
            {
                $this->hasReturn = $has;
                $this->getReturn = $get;
            }

            public function has(mixed $offset): bool
            {
                return $this->hasReturn;
            }

            public function get(mixed $offset): mixed
            {
                return $this->getReturn;
            }

            public function set(mixed $offset, mixed $value): void
            {
                $this->setCalls[$offset] = $value;
            }

            public function remove(mixed $offset): void
            {
                $this->removeCalls[] = $offset;
            }

            public function getSetCalls(): array
            {
                return $this->setCalls;
            }

            public function getRemoveCalls(): array
            {
                return $this->removeCalls;
            }
        };
    }
}
