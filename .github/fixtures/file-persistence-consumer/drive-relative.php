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
file_put_contents($directory . '/native-plain.php', '<?php return ["plain" => true];');
$result = [
    'php' => \PHP_VERSION,
    'drive' => $drive,
    'native_input_supported' => false !== realpath($drive . 'native-plain.php'),
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
            $input = $drive . basename($link) . ('ancestor' === $kind ? '/settings.php' : '');
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

echo json_encode($result, \JSON_THROW_ON_ERROR);
