<?php

declare(strict_types=1);

if ('\\' === \DIRECTORY_SEPARATOR && is_dir($argv[1])) {
    rmdir($argv[1]);
} else {
    unlink($argv[1]);
}

if (! symlink($argv[2], $argv[1])) {
    throw new RuntimeException('The symlink could not be retargeted.');
}
