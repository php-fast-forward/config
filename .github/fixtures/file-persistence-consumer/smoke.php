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

use Composer\InstalledVersions;
use FastForward\Config\Exception\InvalidArgumentException;
use FastForward\Config\PhpFileConfig;

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

check('Windows' === \PHP_OS_FAMILY, 'This smoke test requires a native Windows runtime.');
check(! InstalledVersions::isInstalled('phpunit/phpunit'), 'The consumer must not install PHPUnit.');
$name = 'ff-config-file-' . bin2hex(random_bytes(8));
$roots = [];
$workingDirectory = getcwd();
check(is_string($workingDirectory), 'The current working directory must be accessible.');

try {
    foreach (range('C', 'Z') as $letter) {
        $root = $letter . ':\\' . $name;
        if (is_dir($letter . ':\\') && @mkdir($root)) {
            $roots[] = $root;
        }

        if (2 === count($roots)) {
            break;
        }
    }

    check(2 === count($roots), 'Two writable Windows drives are required for the cross-drive regression.');
    echo 'native drives: ' . substr($roots[0], 0, 2) . ' and ' . substr($roots[1], 0, 2) . \PHP_EOL;

    foreach ($roots as $root) {
        $otherRoot = $root === $roots[0] ? $roots[1] : $roots[0];
        $process = proc_open([\PHP_BINARY, __DIR__ . '/drive-relative.php', $otherRoot], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $root);
        check(is_resource($process), 'The native drive-relative subprocess could not start.');
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        check(0 === proc_close($process), $errors);
        $native = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        echo 'native drive-relative capability: ' . $output . \PHP_EOL;
        foreach ($native['retarget_checks'] as $kind => $case) {
            if ($case['native_input_supported'] ?? false) {
                check($case['passed'], 'A drive-relative symlink froze its ' . $kind . ' target.');
            }
        }

        foreach ($native['directory_checks'] as $case => $passed) {
            check($passed, 'A directory symlink case failed: ' . $case);
        }

        foreach ($native['trailing_checks'] as $case => $result) {
            check($result['passed'], 'A trailing-separator case failed: ' . $case);
        }

        $command = [\PHP_BINARY, '-n', '-d', 'disable_functions=preg_match', __DIR__ . '/resolver-inputs.php'];
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $root);
        check(is_resource($process), 'The disabled-regex subprocess could not start.');
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        check(0 === proc_close($process), $errors);
        $resolver = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        echo 'native resolver inputs: ' . $output . \PHP_EOL;
        check(false === $resolver['preg_match_available'], 'The subprocess did not disable preg_match.');
        foreach (['regex_checks', 'nul_checks'] as $group) {
            foreach ($resolver[$group] as $case => $result) {
                check($result['passed'], 'A resolver input case failed: ' . $case);
            }
        }
    }

    $qualified = $roots[0] . '/qualified.php';
    file_put_contents($qualified, '<?php chdir(' . var_export($roots[1], true) . '); return ["initial" => true];');
    chdir($roots[0]);
    $config = new PhpFileConfig($qualified, persistent: true);
    check(true === $config->get('initial'), 'A fully qualified path did not load the selected file.');
    $config->set('saved', true);
    $config->remove('initial');
    check([
        'saved' => true,
    ] === (require $qualified), 'A fully qualified path changed drives.');
    echo 'passed: fully qualified path across a drive change' . \PHP_EOL;

    $stream = 'file:///' . str_replace('\\', '/', $qualified);
    check(true === (new PhpFileConfig($stream))->get('saved'), 'An explicit leading file stream was not preserved.');
    echo 'passed: explicit leading file stream' . \PHP_EOL;

    $device = '\\\\?\\' . str_replace('/', '\\', $qualified);
    chdir($roots[1]);
    $config = new PhpFileConfig($device, persistent: true);
    check(true === $config->get('saved'), 'An existing DOS device path was not preserved.');
    $config->set('device', true);
    check(true === (require $qualified)['device'], 'A DOS device path wrote to a different target.');
    echo 'passed: fully qualified DOS device path' . \PHP_EOL;

    $uncFile = $roots[0] . '/unc.php';
    file_put_contents($uncFile, '<?php return ["initial" => true];');
    $unc = '\\\\localhost\\' . $roots[0][0] . '$\\' . $name . '\\unc.php';
    if (@is_file($unc)) {
        $config = new PhpFileConfig($unc, persistent: true);
        check(true === $config->get('initial'), 'An existing UNC path did not load its target.');
        chdir($roots[1]);
        $config->set('saved', true);
        $config->remove('initial');
        check([
            'saved' => true,
        ] === (require $uncFile), 'An existing UNC path changed targets.');
        echo 'passed: UNC path across a drive change' . \PHP_EOL;
    } else {
        echo 'skipped: the runner does not expose the loopback administrative UNC share' . \PHP_EOL;
    }

    mkdir($roots[0] . '/drive-relative');
    chdir($roots[0] . '/drive-relative');
    $drive = substr($roots[0], 0, 2);
    $driveExisting = $roots[0] . '/drive-relative/existing.php';
    file_put_contents($driveExisting, '<?php return ["initial" => true];');
    $config = new PhpFileConfig($drive . 'existing.php', persistent: true);
    if (false === realpath($drive . 'existing.php')) {
        try {
            $config->set('saved', true);
            throw new RuntimeException('A drive-relative path unresolved by native PHP was accepted.');
        } catch (InvalidArgumentException) {
            check([
                'initial' => true,
            ] === (require $driveExisting), 'A rejected existing drive-relative path was modified.');
            check(! file_exists($roots[0] . '/drive-relative/.existing.php.lock'), 'A rejected path created a lock.');
        }

        echo 'passed: native-unresolvable drive-relative path rejected without changes' . \PHP_EOL;
    } else {
        $config->set('saved', true);
        check(
            true === (require $driveExisting)['saved'],
            'An existing drive-relative path did not use native resolution.'
        );
        echo 'passed: existing drive-relative path' . \PHP_EOL;
    }

    mkdir($roots[0] . '/unresolved');
    chdir($roots[0] . '/unresolved');
    try {
        (new PhpFileConfig($drive . 'missing.php', persistent: true, defaultConfig: []))->set('saved', true);
        throw new RuntimeException('An unresolved drive-relative path was accepted.');
    } catch (InvalidArgumentException) {
        check(0 === iterator_count(new FilesystemIterator(getcwd())), 'A rejected drive-relative path created files.');
    }

    echo 'passed: unresolved drive-relative path rejected before creation' . \PHP_EOL;

    foreach (['\\', '/'] as $separator) {
        $fileName = '\\' === $separator ? 'backslash.php' : 'slash.php';
        $selected = $roots[0] . '/' . $fileName;
        $other = $roots[1] . '/' . $fileName;
        $otherContent = '<?php return ["other" => true];';
        file_put_contents($selected, '<?php chdir(' . var_export($roots[1], true) . '); return ["initial" => true];');
        file_put_contents($other, $otherContent);
        chdir($roots[0]);

        $config = new PhpFileConfig($separator . $name . $separator . $fileName, persistent: true);
        check(true === $config->get('initial'), 'A root-relative path did not select the initial drive.');
        $config->set('saved', true);
        $config->remove('initial');
        check([
            'saved' => true,
        ] === (require $selected), 'A root-relative path changed to the other drive: ' . $separator);
        check($otherContent === file_get_contents($other), 'A root-relative path modified a competing file.');
        echo 'passed: root-relative ' . $separator . ' path across a drive change' . \PHP_EOL;
    }

    echo 'file persistence runtime smoke: passed on PHP ' . \PHP_VERSION . ' (' . \PHP_OS_FAMILY . ')' . \PHP_EOL;
} finally {
    chdir($workingDirectory);
    foreach ($roots as $root) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            if ($file->isDir() && ! $file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($root);
    }
}
