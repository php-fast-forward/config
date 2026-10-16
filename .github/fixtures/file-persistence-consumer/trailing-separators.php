<?php

declare(strict_types=1);

use FastForward\Config\Exception\InvalidArgumentException;
use FastForward\Config\PhpFileConfig;

return static function (string $directory): array {
    $root = $directory . '/trailing-files';
    mkdir($root);
    $results = [];
    $links = [];
    $separators = '\\' === \DIRECTORY_SEPARATOR ? ['/', '\\'] : ['/'];
    try {
        foreach ($separators as $number => $separator) {
            foreach (['file', 'link input', 'link target'] as $kind) {
                $case = $root . '/' . $number . '-' . str_replace(' ', '-', $kind);
                mkdir($case);
                $file = $case . '/settings.php';
                $link = $case . '/link.php';
                $input = $file . $separator;
                if ('link target' === $kind) {
                    mkdir($file);
                    if (! symlink($file . $separator, $link)) {
                        throw new RuntimeException('A trailing-target symlink could not be created.');
                    }

                    $links[] = $link;
                    rmdir($file);
                    $input = $link;
                }

                $content = '<?php return ["initial" => true, "remove" => true];';
                file_put_contents($file, $content);
                if ('link input' === $kind) {
                    if (! symlink($file, $link)) {
                        throw new RuntimeException('A trailing-input symlink could not be created.');
                    }

                    $links[] = $link;
                    $input = $link . $separator;
                }

                clearstatcache(true);
                $nativeExists = file_exists($input);
                $nativeFile = is_file($input);
                $passed = true;
                if ($nativeFile) {
                    $config = new PhpFileConfig($input, persistent: true);
                    $passed = true === $config->get('initial');
                    $config->set('saved', true);
                    $config->remove('initial');
                    $passed = $passed && [
                        'remove' => true,
                        'saved' => true,
                    ] === (require $file);
                } else {
                    foreach (['get', 'set', 'remove'] as $operation) {
                        $config = new PhpFileConfig(
                            $input,
                            persistent: true,
                            defaultConfig: 'get' === $operation ? null : []
                        );
                        try {
                            if ('get' === $operation) {
                                $config->get('initial');
                            } elseif ('set' === $operation) {
                                $config->set('saved', true);
                            } else {
                                $config->remove('remove');
                            }

                            $passed = false;
                        } catch (InvalidArgumentException) {
                        }
                    }

                    $config = new PhpFileConfig($input, defaultConfig: [
                        'fallback' => true,
                    ]);
                    if ($nativeExists) {
                        try {
                            $config->toArray();
                            $passed = false;
                        } catch (InvalidArgumentException) {
                        }
                    } else {
                        $passed = $passed && [
                            'fallback' => true,
                        ] === $config->toArray();
                    }

                    $passed = $passed && $content === file_get_contents($file);
                }

                $results[$kind . ' ending ' . $separator] = [
                    'native_exists' => $nativeExists,
                    'native_is_file' => $nativeFile,
                    'passed' => $passed && [] === glob($case . '/cfg_*')
                        && ('file' === $kind || is_link($link)),
                ];
            }
        }

        return $results;
    } finally {
        foreach ($links as $link) {
            if ('\\' === \DIRECTORY_SEPARATOR && @rmdir($link)) {
                continue;
            }

            unlink($link);
        }
    }
};
