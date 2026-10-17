<?php

declare(strict_types=1);

use Brick\VarExporter\VarExporter;
use FastForward\Config\Exception\InvalidArgumentException;
use FastForward\Config\PhpFileConfig;

require __DIR__ . '/vendor/autoload.php';

if ('Windows' !== \PHP_OS_FAMILY || false === ($directory = getcwd())) {
    throw new RuntimeException('Native Windows with an accessible working directory is required.');
}

$source = realpath((new ReflectionClass(PhpFileConfig::class))->getFileName());
$roots = [
    'FastForward\\Config\\' => dirname($source),
    'Brick\\VarExporter\\' => dirname((new ReflectionClass(VarExporter::class))->getFileName()),
];
$classMap = [];
foreach ($roots as $prefix => $root) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if ($file->isFile() && 'php' === $file->getExtension()) {
            $relative = substr((string) $file->getPathname(), strlen($root) + 1, -4);
            $classMap[$prefix . str_replace(\DIRECTORY_SEPARATOR, '\\', $relative)] = $file->getPathname();
        }
    }
}

$mapFile = $directory . '/resolver-helper-map.json';
file_put_contents($mapFile, json_encode($classMap, \JSON_THROW_ON_ERROR));
$cases = [];
$append = static function (
    string $function,
    string $kind,
    bool $required,
    array $operations = ['get', 'set', 'remove'],
    ?string $disabled = null
) use (&$cases): void {
    foreach ($operations as $operation) {
        $cases[] = [
            'function' => $function,
            'disabled' => $disabled ?? $function,
            'kind' => $kind,
            'operation' => $operation,
            'required' => $required,
        ];
    }
};

foreach (['str_contains', 'str_starts_with', 'str_ends_with', 'in_array'] as $function) {
    foreach (['file', 'stream'] as $kind) {
        foreach (['get', 'set', 'remove'] as $operation) {
            $required = match ($function) {
                'str_ends_with' => 'file' === $kind || 'get' !== $operation,
                'in_array' => 'stream' === $kind,
                default => true,
            };
            $append($function, $kind, $required, [$operation]);
        }
    }
}

foreach (['file', 'relative', 'root', 'stream'] as $kind) {
    $append('strspn', $kind, true);
}

foreach (['substr', 'rtrim', 'strncasecmp'] as $function) {
    $append($function, 'drive', true);
    $append($function, 'file', false, ['get']);
    $append($function, 'stream', false, ['get']);
}

$append('str_starts_with', 'root', false);
$append('strncasecmp', 'drive', false, disabled: 'strncasecmp,getcwd');
foreach ([
    'str_contains',
    'str_starts_with',
    'str_ends_with',
    'strspn',
    'substr',
    'rtrim',
    'strncasecmp',
    'in_array',
] as $function) {
    $append($function, 'empty', false, ['get']);
}

$checks = [];
foreach ($cases as $index => $case) {
    $caseDirectory = $directory . '/resolver-helper-' . $index;
    mkdir($caseDirectory);
    $file = $caseDirectory . '/settings.php';
    $content = '<?php return ["initial" => true];';
    file_put_contents($file, $content);
    $input = match ($case['kind']) {
        'stream' => 'file:///' . str_replace('\\', '/', $file),
        'relative' => 'settings.php',
        'root' => substr($caseDirectory, 2) . '/settings.php',
        'drive' => substr($caseDirectory, 0, 2) . '..\\' . basename($caseDirectory) . '\\settings.php',
        'empty' => '',
        default => $file,
    };
    $command = [
        \PHP_BINARY, '-n', '-d', 'display_errors=stderr', '-d', 'disable_functions=' . $case['disabled'],
        __DIR__ . '/resolver-function-child.php', $mapFile, $input, $case['operation'], $case['function'], $case['kind'],
        $caseDirectory,
    ];
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, $caseDirectory);
    if (! is_resource($process)) {
        throw new RuntimeException('A disabled-helper subprocess could not start.');
    }

    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (0 !== proc_close($process)) {
        throw new RuntimeException($errors);
    }

    $result = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
    $passed = false === $result['function_available'] && $source === realpath($result['source']);
    if ('strncasecmp,getcwd' === $case['disabled']) {
        $passed = $passed && false === $result['getcwd_available'];
    }

    if ('drive' === $case['kind']) {
        // Require a natively supported form; this is never an adaptive skip.
        $passed = $passed && realpath($file) === $result['native_target'];
    }

    if ($case['required']) {
        $passed = $passed && InvalidArgumentException::class === $result['exception']
            && $content === file_get_contents($file)
            && ['.', '..', 'settings.php'] === scandir($caseDirectory);
    } else {
        $expected = match ($case['operation']) {
            'set' => [
                'initial' => true,
                'saved' => true,
            ],
            'remove' => [],
            default => [
                'initial' => true,
            ],
        };
        $passed = $passed && null === $result['exception'] && $expected === (require $file)
            && ('get' !== $case['operation'] || true === $result['value']);
    }

    $checks[$case['disabled'] . ' ' . $case['kind'] . ' ' . $case['operation']] = [
        'required' => $case['required'],
        'passed' => $passed,
        'function_available' => $result['function_available'],
        'getcwd_available' => $result['getcwd_available'],
        'exception' => $result['exception'],
        'native_target_before_chdir' => $result['native_target_before_chdir'],
        'native_target' => $result['native_target'],
    ];
}

echo json_encode([
    'php' => \PHP_VERSION,
    'zts' => \PHP_ZTS,
    'drive' => substr($directory, 0, 2),
    'source' => $source,
    'source_sha256' => hash_file('sha256', $source),
    'checks' => $checks,
], \JSON_THROW_ON_ERROR);
