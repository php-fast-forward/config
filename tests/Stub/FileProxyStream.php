<?php

declare(strict_types=1);

namespace FastForward\Config\Tests\Stub;

final class FileProxyStream
{
    public mixed $context;

    private mixed $stream;

    /**
     * @param string $path
     * @param string $mode
     *
     * @return bool
     */
    public function stream_open(string $path, string $mode): bool
    {
        $this->stream = fopen(substr($path, strpos($path, '://') + 3), $mode);

        return false !== $this->stream;
    }

    /**
     * @param int $count
     */
    public function stream_read(int $count): string|false
    {
        return fread($this->stream, $count);
    }

    /**
     * @return bool
     */
    public function stream_eof(): bool
    {
        return feof($this->stream);
    }

    /**
     * @return array|false
     */
    public function stream_stat(): array|false
    {
        return fstat($this->stream);
    }

    /**
     * @return bool
     */
    public function stream_set_option(): bool
    {
        return false;
    }

    /**
     * @return void
     */
    public function stream_close(): void
    {
        fclose($this->stream);
    }

    /**
     * @param string $path
     *
     * @return array|false
     */
    public function url_stat(string $path): array|false
    {
        return @stat(substr($path, strpos($path, '://') + 3));
    }
}
