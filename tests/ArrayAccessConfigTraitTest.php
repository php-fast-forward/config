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

    /**
     * @return void
     */
    public function testOffsetExistsWillCallHasMethod(): void
    {
        $object = $this->createTraitInstance(has: true);
        self::assertTrue($object->offsetExists('key'));

        $object = $this->createTraitInstance(has: false);
        self::assertFalse($object->offsetExists('key'));
    }

    /**
     * @return void
     */
    public function testOffsetGetWillCallGetMethod(): void
    {
        $object = $this->createTraitInstance(get: 'foo');
        self::assertSame('foo', $object->offsetGet('bar'));
    }

    /**
     * @return void
     */
    public function testOffsetSetWillCallSetMethod(): void
    {
        $object = $this->createTraitInstance();
        $object->offsetSet('alpha', 'beta');

        self::assertSame([
            'alpha' => 'beta',
        ], $object->getSetCalls());
    }

    /**
     * @return void
     */
    public function testOffsetUnsetWillCallRemoveMethod(): void
    {
        $object = $this->createTraitInstance();
        $object->offsetUnset('delta');

        self::assertSame(['delta'], $object->getRemoveCalls());
    }

    /**
     * @param bool $has
     * @param mixed $get
     *
     * @return ArrayAccess
     */
    private function createTraitInstance(bool $has = false, mixed $get = null): ArrayAccess
    {
        return new class ($has, $get) implements ArrayAccess {
            use ArrayAccessConfigTrait;

            private array $setCalls = [];

            private array $removeCalls = [];

            /**
             * @param bool $hasReturn
             * @param mixed $getReturn
             */
            public function __construct(
                private bool $hasReturn,
                private mixed $getReturn
            ) {}

            /**
             * @param mixed $offset
             *
             * @return bool
             */
            public function has(mixed $offset): bool
            {
                return $this->hasReturn;
            }

            /**
             * @param mixed $offset
             *
             * @return mixed
             */
            public function get(mixed $offset): mixed
            {
                return $this->getReturn;
            }

            /**
             * @param mixed $offset
             * @param mixed $value
             *
             * @return void
             */
            public function set(mixed $offset, mixed $value): void
            {
                $this->setCalls[$offset] = $value;
            }

            /**
             * @param mixed $offset
             *
             * @return void
             */
            public function remove(mixed $offset): void
            {
                $this->removeCalls[] = $offset;
            }

            /**
             * @return array
             */
            public function getSetCalls(): array
            {
                return $this->setCalls;
            }

            /**
             * @return array
             */
            public function getRemoveCalls(): array
            {
                return $this->removeCalls;
            }
        };
    }
}
