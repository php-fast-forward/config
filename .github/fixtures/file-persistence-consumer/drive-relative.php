<?php

declare(strict_types=1);

use FastForward\Config\Exception\InvalidArgumentException;
use FastForward\Config\PhpFileConfig;

require __DIR__ . '/vendor/autoload.php';

$directory = getcwd();
if (! is_string($directory)) {
    throw new RuntimeException('The native subprocess working directory is unavailable.');
}

$drive = substr($directory, 0, 2);
$directoryChecks = (require __DIR__ . '/dangling-directories.php')($directory);
$trailingChecks = (require __DIR__ . '/trailing-separators.php')($directory);
file_put_contents($directory . '/native-plain.php', '<?php return ["plain" => true];');
$prefix = null;
$forms = [];
foreach ([
    'bare' => '',
    'dot slash' => './',
    'dot backslash' => '.\\',
    'parent' => '..\\' . basename($directory) . '\\',
] as $name => $candidate) {
    $supported = false !== realpath($drive . $candidate . 'native-plain.php');
    $forms[$name] = $supported;
    if ($supported && null === $prefix) {
        $prefix = $candidate;
    }
}

$result = [
    'php' => \PHP_VERSION,
    'zts' => \PHP_ZTS,
    'drive' => $drive,
    'native_input_supported' => null !== $prefix,
    'native_forms' => $forms,
    'native_drive_directory_supported' => false !== realpath($drive . '.'),
    'retarget_checks' => [],
];

if (! $result['native_input_supported']) {
    try {
        (new PhpFileConfig($drive . 'native-plain.php', persistent: true))->get('plain');
        throw new RuntimeException('An input unsupported by native realpath was accepted.');
    } catch (InvalidArgumentException) {
        $result['rejection_verified'] = true;
    }
} else {
    foreach (['leaf', 'ancestor'] as $kind) {
        $first = $directory . '/native-' . $kind . '-first';
        $second = $directory . '/native-' . $kind . '-second';
        $link = $directory . '/native-' . $kind . '-link';
        if ('ancestor' === $kind) {
            mkdir($first);
            mkdir($second);
            $firstFile = $first . '/settings.php';
            $secondFile = $second . '/settings.php';
        } else {
            $firstFile = $first;
            $secondFile = $second;
        }

        file_put_contents($firstFile, '<?php return ["first" => true];');
        file_put_contents($secondFile, '<?php return ["second" => true];');
        if (! @symlink($first, $link)) {
            $result['retarget_checks'][$kind] = [
                'symlink_available' => false,
            ];

            continue;
        }

        try {
            $input = $drive . $prefix . basename($link) . ('ancestor' === $kind ? '/settings.php' : '');
            if (false === realpath($input)) {
                $result['retarget_checks'][$kind] = [
                    'native_input_supported' => false,
                ];

                continue;
            }

            $config = new PhpFileConfig($input, persistent: true);
            if (true !== $config->get('first')) {
                throw new RuntimeException('The initial drive-relative symlink target was not loaded.');
            }

            if ('ancestor' === $kind) {
                rmdir($link);
            } else {
                unlink($link);
            }

            if (! symlink($second, $link)) {
                throw new RuntimeException('The drive-relative symlink could not be retargeted.');
            }

            $config->set('saved', true);
            $config->remove('second');
            $result['retarget_checks'][$kind] = [
                'native_input_supported' => true,
                'passed' => [
                    'first' => true,
                ] === (require $firstFile) && [
                    'saved' => true,
                ] === (require $secondFile),
            ];
        } finally {
            if ('ancestor' === $kind) {
                rmdir($link);
            } else {
                unlink($link);
            }
        }
    }
}

if (null !== $prefix) {
    chdir($argv[1]);
    $input = $drive . $prefix . 'native-plain.php';
    $result['other_drive_input_supported'] = false !== realpath($input);
    if ($result['other_drive_input_supported']) {
        $config = new PhpFileConfig($input, persistent: true);
        if (true !== $config->get('plain')) {
            throw new RuntimeException('A drive-relative input did not retain its native drive directory.');
        }

        $config->set('other_drive', true);
        if (true !== (require $directory . '/native-plain.php')['other_drive']) {
            throw new RuntimeException('A drive-relative input was persisted on the wrong drive.');
        }

        $result['other_drive_passed'] = true;
    }
}

$result['directory_checks'] = $directoryChecks;
$result['trailing_checks'] = $trailingChecks;

echo json_encode($result, \JSON_THROW_ON_ERROR);
