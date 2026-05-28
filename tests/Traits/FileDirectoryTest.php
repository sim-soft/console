<?php

declare(strict_types=1);

namespace Tests\Traits;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Simsoft\Console\Traits\FileDirectory;

class FileDirectoryTest extends TestCase
{
    use FileDirectory;

    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'simsoft_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        // Clean up created directories
        if (is_dir($this->tempDir)) {
            $this->removeDir($this->tempDir);
        }
    }

    private function removeDir(string $dir): void
    {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    // --- mkdir creates directory ---

    public function testMkdirCreatesDirectory(): void
    {
        $this->assertDirectoryDoesNotExist($this->tempDir);
        $this->mkdir($this->tempDir);
        $this->assertDirectoryExists($this->tempDir);
    }

    public function testMkdirCreatesNestedDirectories(): void
    {
        $nested = $this->tempDir . DIRECTORY_SEPARATOR . 'level1' . DIRECTORY_SEPARATOR . 'level2';
        $this->mkdir($nested);
        $this->assertDirectoryExists($nested);
    }

    public function testMkdirDoesNothingIfDirectoryExists(): void
    {
        mkdir($this->tempDir, 0777, true);
        $this->assertDirectoryExists($this->tempDir);

        // Should not throw
        $this->mkdir($this->tempDir);
        $this->assertDirectoryExists($this->tempDir);
    }

    // --- Custom error message ---

    public function testMkdirCustomErrorMessage(): void
    {
        // Create a file at the path to prevent directory creation
        $parentDir = $this->tempDir;
        mkdir($parentDir, 0777, true);

        $filePath = $parentDir . DIRECTORY_SEPARATOR . 'blocker';
        file_put_contents($filePath, 'block');

        // Try to create a directory inside the file (impossible)
        $impossiblePath = $filePath . DIRECTORY_SEPARATOR . 'subdir';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Custom error: ' . $impossiblePath);
        $this->mkdir($impossiblePath, 0777, 'Custom error: {path}');
    }

    // --- Default error message ---

    public function testMkdirDefaultErrorMessage(): void
    {
        $parentDir = $this->tempDir;
        mkdir($parentDir, 0777, true);

        $filePath = $parentDir . DIRECTORY_SEPARATOR . 'blocker';
        file_put_contents($filePath, 'block');

        $impossiblePath = $filePath . DIRECTORY_SEPARATOR . 'subdir';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to create directory: ' . $impossiblePath);
        $this->mkdir($impossiblePath);
    }
}
