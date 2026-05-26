<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Caching;

/**
 * File-based cache for generated runtime code.
 *
 * Use an application-owned directory with strict permissions. Avoid shared writable temp locations.
 */
class FileCache implements CacheInterface
{
    public function __construct(
        private readonly string $cacheDirectory,
    ) {
        if (!is_dir($this->cacheDirectory)) {
            mkdir($this->cacheDirectory, 0o755, true);
        }
    }

    public function has(string $className, string $type): bool
    {
        return file_exists($this->getCachePath($className, $type));
    }

    public function get(string $className, string $type): ?object
    {
        $path = $this->getCachePath($className, $type);

        if (!file_exists($path)) {
            return null;
        }

        $cached = require $path;

        return is_object($cached) ? $cached : null;
    }

    public function set(string $className, string $type, string $code): void
    {
        $path = $this->getCachePath($className, $type);
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0o755, true);
        }

        file_put_contents($path, $code);
    }

    public function invalidate(string $className, string $type): void
    {
        $path = $this->getCachePath($className, $type);

        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function clear(): void
    {
        $this->deleteDirectory($this->cacheDirectory);
        mkdir($this->cacheDirectory, 0o755, true);
    }

    private function getCachePath(string $className, string $type): string
    {
        $hash = md5($className);
        $safeName = str_replace('\\', '_', $className);

        return $this->cacheDirectory.DIRECTORY_SEPARATOR.$type.DIRECTORY_SEPARATOR.$safeName.'_'.$hash.'.php';
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
