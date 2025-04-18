<?php

declare(strict_types=1);

namespace FastForward\Config\Tests;

use FastForward\Config\ArrayConfig;
use FastForward\Config\DirectoryConfig;
use FastForward\Config\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DirectoryConfig::class)]
#[UsesClass(ArrayConfig::class)]
#[UsesClass(InvalidArgumentException::class)]
final class DirectoryConfigTest extends TestCase
{
    #[Test]
    public function testConstructorWillThrowExceptionForUnreadableDirectory()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist or is not readable');

        new DirectoryConfig(uniqid('/path/that/does/not/exist/', true));
    }

    #[Test]
    public function testConstructorWillSucceedForValidDirectory()
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('config_', true);
        mkdir($dir);
        file_put_contents($dir . '/config.php', '<?php return ["foo" => "bar"];');

        $config = new DirectoryConfig($dir);
        $resolved = $config();

        unlink($dir . '/config.php');
        rmdir($dir);

        $this->assertIsArray($resolved->toArray());
        $this->assertArrayHasKey('foo', $resolved->toArray());
    }
}
