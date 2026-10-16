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
            throw new RuntimeException('A directory regression symlink could not be created.');
        }

        $links[$link] = readlink($link);
    };

    try {
        foreach (['relative', 'absolute'] as $kind) {
            $case = $root . '/' . $kind;
            mkdir($case . '/missing', 0o777, true);
            chdir($case);
            for ($index = 2; $index >= 0; --$index) {
                $target = $index < 2 ? 'link' . ($index + 1) : 'missing';
                $createLink('absolute' === $kind ? $case . '/' . $target : $target, $case . '/link' . $index);
            }

            rmdir($case . '/missing');
            $config = new PhpFileConfig($case . '/link0/nested/settings.php', persistent: true, defaultConfig: [
                'initial' => true,
            ]);
            $checks[$kind . ' defaults'] = true === $config->get('initial') && ! is_dir($case . '/missing');
            $config->set('saved', true);
            $config->remove('initial');
            $checks[$kind . ' publication'] = [
                'saved' => true,
            ] === (require $case . '/missing/nested/settings.php');
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
        (new PhpFileConfig($case . '/alias/../missing/settings.php', persistent: true, defaultConfig: []))
            ->set('saved', true);
        $checks['parent after symlink'] = ! is_dir($case . '/missing') && [
            'saved' => true,
        ] === (require $case . '/deep/missing/settings.php');

        $case = $root . '/cycle';
        mkdir($case);
        chdir($case);
        $createLink('second', $case . '/first');
        $createLink('first', $case . '/second');
        try {
            (new PhpFileConfig($case . '/first/settings.php', persistent: true, defaultConfig: []))
                ->set('saved', true);
            $checks['cycle rejected'] = false;
        } catch (InvalidArgumentException) {
            $checks['cycle rejected'] = 2 === iterator_count(new FilesystemIterator($case));
        }

        $checks['links preserved'] = true;
        foreach ($links as $link => $target) {
            $checks['links preserved'] = $checks['links preserved'] && is_link($link) && $target === readlink($link);
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
