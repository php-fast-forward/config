<?php

declare(strict_types=1);

use FastForward\Config\PhpFileConfig;

$classMap = json_decode(file_get_contents($argv[1]), true, 512, \JSON_THROW_ON_ERROR);
spl_autoload_register(static function (string $class) use ($classMap): void {
    if (isset($classMap[$class])) {
        require $classMap[$class];
    }
});

$input = $argv[2];
if ('nul' === ($argv[5] ?? null)) {
    $input .= "\0suffix";
}

if (isset($argv[6])) {
    chdir($argv[6]);
}

try {
    $config = new PhpFileConfig($input, persistent: true, defaultConfig: [
        'initial' => true,
    ]);
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
$result['source'] = (new ReflectionClass(PhpFileConfig::class))->getFileName();
echo json_encode($result, \JSON_THROW_ON_ERROR);
