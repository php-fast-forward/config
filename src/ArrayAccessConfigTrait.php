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

namespace FastForward\Config;

use Dflydev\DotAccessData\Data;

/**
 * Trait ArrayAccessConfigTrait.
 *
 * This trait provides array-like access to configuration data.
 * It MUST be used in classes that implement \ArrayAccess and provide
 * the corresponding methods: `get`, `set`, `has`, and `remove`.
 *
 * @internal
 *
 * @see \ArrayAccess
 */
trait ArrayAccessConfigTrait
{
    /**
     * Determines whether the given offset exists in the configuration data.
     *
     * This method SHALL return true if the offset is present, false otherwise.
     *
     * @param mixed $offset the offset to check for existence
     *
     * @return bool true if the offset exists, false otherwise
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * Retrieves the value associated with the given offset.
     *
     * This method MUST return the value mapped to the specified offset.
     * If the offset does not exist, behavior SHALL depend on the implementation
     * of the `get` method.
     *
     * @param mixed $offset the offset to retrieve
     *
     * @return mixed the value at the given offset
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * Sets the value for the specified offset.
     *
     * This method SHALL assign the given value to the specified offset.
     *
     * @param mixed $offset the offset at which to set the value
     * @param mixed $value  the value to set
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set($offset, $value);
    }

    /**
     * Unsets the specified offset.
     *
     * This method SHALL remove the specified offset and its associated value.
     *
     * @param mixed $offset the offset to remove
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->remove($offset);
    }
}
