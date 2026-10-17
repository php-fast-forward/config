<?php

declare(strict_types=1);

use FastForward\Config\Exception\InvalidArgumentException;
use FastForward\Config\PhpFileConfig;

require __DIR__ . '/vendor/autoload.php';

$directory = getcwd();
if (! is_string($directory) || function_exists('preg_match')) {
    throw new RuntimeException('A native directory and disabled preg_match are required.');
}

$file = $directory . '/without-regex.php';
$content = '<?php return ["initial" => true];';
file_put_contents($file, $content);
$suffix = substr($file, 2);
$paths = [
    'qualified' => [$file, true],
    'relative' => ['without-regex.php', true],
    'drive-relative' => [substr($directory, 0, 2) . '..\\' . basename($directory) . '\\without-regex.php', true],
    'rooted backslash' => ['\\' . ltrim($suffix, '\\/'), true],
    'rooted slash' => ['/' . ltrim($suffix, '\\/'), true],
    'file stream' => ['file:///' . str_replace('\\', '/', $file), true],
    'DOS device' => ['\\\\?\\' . str_replace('/', '\\', $file), false],
];
$unc = '\\\\localhost\\' . $directory[0] . '$\\' . ltrim(str_replace('/', '\\', $suffix), '\\');
if (@is_file($unc)) {
    $paths['UNC'] = [$unc, false];
}

$regexChecks = [];
foreach ($paths as $name => [$input, $required]) {
    foreach (['get', 'set', 'remove'] as $operation) {
        file_put_contents($file, $content);
        $entries = scandir($directory);
        $exception = null;
        try {
            $config = new PhpFileConfig($input, persistent: true, defaultConfig: []);
            if ('get' === $operation) {
                $config->get('initial');
            } elseif ('set' === $operation) {
                $config->set('saved', true);
            } else {
                $config->remove('initial');
            }
        } catch (Throwable $throwable) {
            $exception = $throwable::class;
        }

        $regexChecks[$name . ' ' . $operation] = [
            'exception' => $exception,
            'passed' => $required
                ? InvalidArgumentException::class === $exception && $content === file_get_contents($file)
                    && $entries === scandir($directory)
                : null === $exception && (match ($operation) {
                    'set' => [
                        'initial' => true,
                        'saved' => true,
                    ],
                    'remove' => [],
                    default => [
                        'initial' => true,
                    ],
                }) === (require $file),
        ];
    }
}

$nulChecks = [];
foreach ([
    'terminal' => $file . "\0suffix",
    'ancestor' => $directory . "/nul-uncreated/invalid\0/settings.php",
    'stream' => 'file:///' . str_replace('\\', '/', $directory) . "/nul-uncreated/invalid\0/settings.php",
] as $name => $input) {
    foreach (['get', 'defaults', 'set', 'remove'] as $operation) {
        file_put_contents($file, $content);
        $entries = scandir($directory);
        $exception = null;
        $result = null;
        try {
            $config = new PhpFileConfig($input, persistent: true, defaultConfig: 'get' === $operation ? null : [
                'fallback' => true,
            ]);
            if ('defaults' === $operation) {
                $result = $config->get('fallback');
            } elseif ('get' === $operation) {
                $config->get('initial');
            } elseif ('set' === $operation) {
                $config->set('saved', true);
            } else {
                $config->remove('fallback');
            }
        } catch (Throwable $throwable) {
            $exception = $throwable::class;
        }

        $nulChecks[$name . ' ' . $operation] = [
            'exception' => $exception,
            'passed' => ('defaults' === $operation
                ? null === $exception && true === $result
                : InvalidArgumentException::class === $exception)
                && $content === file_get_contents($file) && $entries === scandir($directory)
                && ! file_exists($directory . '/nul-uncreated'),
        ];
    }
}

echo json_encode([
    'php' => \PHP_VERSION,
    'drive' => substr($directory, 0, 2),
    'preg_match_available' => function_exists('preg_match'),
    'regex_checks' => $regexChecks,
    'nul_checks' => $nulChecks,
], \JSON_THROW_ON_ERROR);
