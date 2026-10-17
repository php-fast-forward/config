<?php

declare(strict_types=1);

use FastForward\Config\PhpFileConfig;
use FastForward\Config\Tests\Stub\FileProxyStream;

$loader = require $argv[1];
$loader->setPsr4('FastForward\\Config\\', dirname(__DIR__, 2) . '/src');
$loader->setPsr4('FastForward\\Config\\Tests\\', dirname(__DIR__));

try {
    if (isset($argv[5]) && ! stream_wrapper_register($argv[5], FileProxyStream::class)) {
        throw new RuntimeException('The exact-case stream fixture could not be registered.');
    }

    $config = new PhpFileConfig($argv[2], persistent: true, defaultConfig: []);
    $value = null;
    if ('set' === $argv[3]) {
        $config->set('saved', true);
    } elseif ('remove' === $argv[3]) {
        $config->remove('initial');
    } else {
        $value = $config->get('initial');
    }

    $result = [
        'exception' => null,
        'value' => $value,
    ];
} catch (Throwable $throwable) {
    $result = [
        'exception' => $throwable::class,
        'message' => $throwable->getMessage(),
    ];
}

$result['function_available'] = function_exists($argv[4]);

echo json_encode($result, \JSON_THROW_ON_ERROR);
