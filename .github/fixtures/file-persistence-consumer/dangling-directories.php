<?php

declare(strict_types=1);

use FastForward\Config\Exception\InvalidArgumentException;
use FastForward\Config\PhpFileConfig;

return static function (string $directory): array {
    $root = $directory . '/directory-links';
    mkdir($root);
    $links = [];
    $checks = [];
    $workingDirectory = getcwd();
    $createLink = static function (string $target, string $link) use (&$links): void {
        if (! symlink($target, $link)) {
            throw new RuntimeException('A regression symlink could not be created: ' . $target . ' -> ' . $link);
        }

        $links[$link] = @readlink($link);
    };

    try {
        foreach (['relative', 'absolute'] as $kind) {
            $nativeRelative = '\\' === \DIRECTORY_SEPARATOR && 'relative' === $kind;
            $case = $nativeRelative ? $directory : $root . '/' . $kind;
            $prefix = $nativeRelative ? 'relative-' : '';
            $length = $nativeRelative ? 1 : 3;
            mkdir($case . '/' . $prefix . 'missing', 0o777, true);
            chdir($case);
            for ($index = $length - 1; $index >= 0; --$index) {
                $target = $prefix . ($index + 1 < $length ? 'link' . ($index + 1) : 'missing');
                $createLink('absolute' === $kind ? $case . '/' . $target : $target, $case . '/' . $prefix . 'link' . $index);
            }

            rmdir($case . '/' . $prefix . 'missing');
            $config = new PhpFileConfig($case . '/' . $prefix . 'link0/nested/settings.php', persistent: true, defaultConfig: [
                'initial' => true,
            ]);
            if ($nativeRelative && false === @readlink($case . '/' . $prefix . 'link0')) {
                $rejected = 0;
                foreach (['get', 'set', 'remove'] as $operation) {
                    try {
                        if ('get' === $operation) {
                            $config->get('initial');
                        } elseif ('set' === $operation) {
                            $config->set('saved', true);
                        } else {
                            $config->remove('initial');
                        }
                    } catch (InvalidArgumentException) {
                        ++$rejected;
                    }
                }

                $checks['native relative readlink unavailable; rejected before creation'] = 3 === $rejected
                    && ! is_dir($case . '/' . $prefix . 'missing');
                for ($index = 0; $index < $length; ++$index) {
                    $links[$case . '/' . $prefix . 'link' . $index] = false;
                }

                continue;
            }

            $checks[$kind . ' defaults'] = true === $config->get('initial') && ! is_dir(
                $case . '/' . $prefix . 'missing'
            );
            $config->set('saved', true);
            $config->remove('initial');
            $checks[$kind . ' publication'] = [
                'saved' => true,
            ] === (require $case . '/' . $prefix . 'missing/nested/settings.php');
        }

        $case = $root . '/repeated';
        mkdir($case);
        chdir($case);
        $createLink('.', $case . '/alias');
        (new PhpFileConfig($case . '/alias/alias/missing/settings.php', persistent: true, defaultConfig: []))
            ->set('saved', true);
        $checks['repeated alias'] = [
            'saved' => true,
        ] === (require $case . '/missing/settings.php');

        $case = $root . '/parent-segment';
        mkdir($case . '/deep/subdir', 0o777, true);
        $createLink($case . '/deep/subdir', $case . '/alias');
        $nativeParent = realpath($case . '/alias/..');
        if (false === $nativeParent) {
            throw new RuntimeException('The native parent of an existing directory symlink is unavailable.');
        }

        (new PhpFileConfig($case . '/alias/../missing/settings.php', persistent: true, defaultConfig: []))
            ->set('saved', true);
        $unexpected = $nativeParent === realpath($case) ? $case . '/deep/missing' : $case . '/missing';
        $checks['parent after symlink matches native resolution'] = ! is_dir($unexpected) && [
            'saved' => true,
        ] === (require $nativeParent . '/missing/settings.php');

        $case = $root . '/cycle';
        mkdir($case . '/physical', 0o777, true);
        $createLink($case . '/physical', $case . '/first');
        $createLink($case . '/first', $case . '/second');
        rmdir($case . '/physical');
        if (! rename($case . '/second', $case . '/physical')) {
            throw new RuntimeException('The directory symlink cycle fixture could not be completed.');
        }

        unset($links[$case . '/second']);
        $links[$case . '/physical'] = @readlink($case . '/physical');
        try {
            (new PhpFileConfig($case . '/first/settings.php', persistent: true, defaultConfig: []))
                ->set('saved', true);
            $checks['cycle rejected'] = false;
        } catch (InvalidArgumentException) {
            $checks['cycle rejected'] = 2 === iterator_count(new FilesystemIterator($case));
        }

        $checks['links preserved'] = true;
        foreach ($links as $link => $target) {
            $checks['links preserved'] = $checks['links preserved'] && is_link($link) && $target === @readlink($link);
        }

        return $checks;
    } finally {
        chdir($workingDirectory);
        foreach (array_keys($links) as $link) {
            if ('\\' === \DIRECTORY_SEPARATOR && @rmdir($link)) {
                continue;
            }

            unlink($link);
        }
    }
};
