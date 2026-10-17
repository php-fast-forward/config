<?php

declare(strict_types=1);

use FastForward\Config\PhpFileConfig;

// A literal class map keeps the disabled helper out of Composer's own bootstrap.
$classMap = json_decode(file_get_contents($argv[1]), true, 512, \JSON_THROW_ON_ERROR);
spl_autoload_register(static function (string $class) use ($classMap): void {
    if (isset($classMap[$class])) {
        require $classMap[$class];
    }
});

$result = [
    'function_available' => function_exists($argv[4]),
    'getcwd_available' => function_exists('getcwd'),
    'native_target_before_chdir' => realpath($argv[2]),
];

if (! chdir($argv[6])) {
    throw new RuntimeException('The native helper case directory could not be selected.');
}

$result['native_target'] = realpath($argv[2]);

try {
    $config = new PhpFileConfig($argv[2], persistent: true, defaultConfig: [
        'initial' => 'empty' === $argv[5],
    ]);
    $value = null;
    if ('set' === $argv[3]) {
        $config->set('saved', true);
    } elseif ('remove' === $argv[3]) {
        $config->remove('initial');
    } else {
        $value = $config->get('initial');
    }

    $result['exception'] = null;
    $result['value'] = $value;
} catch (Throwable $throwable) {
    $result['exception'] = $throwable::class;
    $result['message'] = $throwable->getMessage();
}

$result['source'] = (new ReflectionClass(PhpFileConfig::class))->getFileName();
echo json_encode($result, \JSON_THROW_ON_ERROR);
