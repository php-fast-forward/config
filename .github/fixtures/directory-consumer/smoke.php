<?php

declare(strict_types=1);

/**
 * This file is part of php-fast-forward/config.
 *
 * This source file is subject to the license bundled
 * with this source code in the file LICENSE.
 *
 * @copyright Copyright (c) 2025-2026 Felipe Sayão Lobato Abreu <github@mentordosnerds.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 *
 * @see       https://github.com/php-fast-forward/config
 * @see       https://github.com/php-fast-forward
 * @see       https://datatracker.ietf.org/doc/html/rfc2119
 */

use FastForward\Config\DirectoryConfig;
use FastForward\Config\RecursiveDirectoryConfig;

use function FastForward\Config\config;
use function FastForward\Config\configDir;

require __DIR__ . '/vendor/autoload.php';

/**
 * @param bool $condition
 * @param string $message
 *
 * @return void
 *
 * @throws RuntimeException
 */
function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$directory = sys_get_temp_dir() . '/ff-directory-smoke-' . bin2hex(random_bytes(8));
mkdir($directory);

try {
    foreach (['config[1]', 'config{one,two}', 'configuração[um]'] as $name) {
        $root = $directory . '/' . $name;
        mkdir($root . '/one/two/three', 0o777, true);
        foreach ([
            '' => 0,
            '/one' => 1,
            '/one/two' => 2,
            '/one/two/three' => 3,
        ] as $suffix => $depth) {
            $data = [
                'level' . $depth => true,
                'winner' => $depth,
                'items' => [$depth],
            ];
            file_put_contents($root . $suffix . '/app.php', '<?php return ' . var_export($data, true) . ';');
        }

        foreach (['config1', 'configone', 'configXone,twoY', 'configuraçãou'] as $sibling) {
            if (! is_dir($directory . '/' . $sibling)) {
                mkdir($directory . '/' . $sibling);
            }

            file_put_contents($directory . '/' . $sibling . '/outside.php', '<?php return ["outside" => true];');
        }

        foreach ([new DirectoryConfig($root), configDir($root)] as $source) {
            check(0 === $source->get('winner'), 'Non-recursive discovery must stay at the root: ' . $name);
            check(! $source->has('outside'), 'A sibling directory contributed configuration: ' . $name);
        }

        foreach ([new RecursiveDirectoryConfig($root), configDir($root, recursive: true), config($root)] as $source) {
            check(3 === $source->get('winner'), 'Recursive discovery missed the deepest directory: ' . $name);
            check([0, 1, 2, 3] === $source->get('items'), 'Recursive merge order changed: ' . $name);
            check(! $source->has('outside'), 'A sibling directory contributed configuration: ' . $name);
        }
    }

    $custom = new class ($directory . '/config{one,two}') extends DirectoryConfig {
        protected const PATTERN = 'one/two/three/*.php';
    };
    check(3 === $custom->get('winner'), 'Relative filename-pattern overrides must remain supported.');

    echo 'directory runtime smoke: passed on PHP ' . \PHP_VERSION . ' (' . \PHP_OS_FAMILY . ')' . \PHP_EOL;
} finally {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $file) {
        if ($file->isDir() && ! $file->isLink()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }

    rmdir($directory);
}
