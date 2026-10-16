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

namespace FastForward\Config\SkillExamples\Fixtures;

use DateInterval;
use InvalidArgumentException;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException as CacheInvalidArgumentException;

/**
 * Example-only retaining cache. It deliberately has no TTL implementation.
 */
final class MemoryCache implements CacheInterface
{
    private array $values = [];

    /**
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);

        return $this->values[$key] ?? $default;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int|DateInterval|null $ttl
     *
     * @return bool
     */
    public function set(string $key, mixed $value, int|DateInterval|null $ttl = null): bool
    {
        $this->validateKey($key);
        $this->values[$key] = $value;

        return true;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function delete(string $key): bool
    {
        $this->validateKey($key);
        unset($this->values[$key]);

        return true;
    }

    /**
     * @return bool
     */
    public function clear(): bool
    {
        $this->values = [];

        return true;
    }

    /**
     * @param iterable $keys
     * @param mixed $default
     *
     * @return iterable
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->get($key, $default);
        }
    }

    /**
     * @param iterable $values
     * @param int|DateInterval|null $ttl
     *
     * @return bool
     */
    public function setMultiple(iterable $values, int|DateInterval|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /**
     * @param iterable $keys
     *
     * @return bool
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        $this->validateKey($key);

        return \array_key_exists($key, $this->values);
    }

    /**
     * @param string $key
     *
     * @return void
     */
    private function validateKey(string $key): void
    {
        if ('' === $key || false !== strpbrk($key, '{}()/\\@:')) {
            throw new class (
                'The example requires a nonempty PSR-16 key without reserved characters.'
            ) extends InvalidArgumentException implements CacheInvalidArgumentException {};
        }
    }
}
