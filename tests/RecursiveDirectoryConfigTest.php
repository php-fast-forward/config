<?php

declare(strict_types=1);

namespace FastForward\Config\Tests;

use FastForward\Config\ArrayConfig;
use FastForward\Config\Exception\InvalidArgumentException;
use FastForward\Config\RecursiveDirectoryConfig;
use Laminas\ConfigAggregator\ConfigAggregator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecursiveDirectoryConfig::class)]
#[UsesClass(ArrayConfig::class)]
#[UsesClass(InvalidArgumentException::class)]
final class RecursiveDirectoryConfigTest extends TestCase
{
    #[Test]
    public function testConstructorWillThrowExceptionForUnreadableRootDirectory()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist or is not readable');

        new RecursiveDirectoryConfig('/non/existing/path');
    }

    #[Test]
    public function testConstructorWillAggregateNestedPhpFiles()
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('recursive_config_', true);
        $nested = $base . '/nested';
        mkdir($nested, 0777, true);

        file_put_contents($nested . '/config.php', '<?php return ["nested" => true];');
        file_put_contents($base . '/base.php', '<?php return ["base" => 1];');

        $config = new RecursiveDirectoryConfig($base);
        $resolved = $config();

        unlink($nested . '/config.php');
        unlink($base . '/base.php');
        rmdir($nested);
        rmdir($base);

        $this->assertIsArray($resolved->toArray());
        $this->assertSame(['base' => 1, 'nested' => true], $resolved->toArray());
    }
}
