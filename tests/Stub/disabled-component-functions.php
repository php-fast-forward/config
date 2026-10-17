<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use FastForward\Config\PhpFileConfig;

// Composer's generated entrypoint itself needs dirname; register its real loader directly.
require $argv[1] . '/composer/ClassLoader.php';
$loader = new ClassLoader($argv[1]);
$loader->setPsr4('FastForward\\Config\\', $argv[2]);
$loader->register();

try {
    $config = new PhpFileConfig($argv[3], defaultConfig: 'file' === $argv[4] ? null : [
        'initial' => true,
    ]);
    $result = [
        'exception' => null,
        'value' => $config->get('initial'),
    ];
} catch (Throwable $throwable) {
    $result = [
        'exception' => $throwable::class,
        'message' => $throwable->getMessage(),
    ];
}

$result['function_available'] = function_exists($argv[5]);
echo json_encode($result, \JSON_THROW_ON_ERROR);
