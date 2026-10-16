<?php

declare(strict_types=1);

use FastForward\Config\PhpFileConfig;

$loader = require $argv[1];
$loader->setPsr4('FastForward\\Config\\', dirname(__DIR__, 2) . '/src');

try {
    $config = new PhpFileConfig($argv[2], persistent: true, defaultConfig: []);
    if ('set' === $argv[3]) {
        $config->set('saved', true);
    } elseif ('remove' === $argv[3]) {
        $config->remove('initial');
    } else {
        $config->get('initial');
    }

    $result = [
        'exception' => null,
    ];
} catch (Throwable $throwable) {
    $result = [
        'exception' => $throwable::class,
        'message' => $throwable->getMessage(),
    ];
}

$result['function_available'] = function_exists($argv[4]);

echo json_encode($result, \JSON_THROW_ON_ERROR);
