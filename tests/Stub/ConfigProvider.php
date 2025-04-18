<?php

namespace FastForward\Config\Tests\Stub;

final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'key' => 'value',
        ];
    }
}
