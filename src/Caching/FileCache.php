<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Caching;

/**
 * File-based cache for generated runtime code.
 *
 * Use an application-owned directory with strict permissions. Avoid shared writable temp locations.
 *
 * Writes are atomic (temp file + rename), so concurrent processes never load a partially written file.
 */
class FileCache implements CacheInterface
{
    public function __construct(
        private readonly string $cacheDirectory,
    ) {
        $this->ensureDirectory($this->cacheDirectory);
    }

    public function has(string $className, string $type): bool
    {
        return file_exists($this->getCachePath($className, $type));
    }

    /**
     * Returns null when the entry is missing or broken; broken entries are removed so they can be regenerated.
     */
    public function get(string $className, string $type): ?object
    {
        $path = $this->getCachePath($className, $type);

        if (!is_file($path)) {
            return null;
        }

        try {
            $cached = require $path;
        } catch (\Error) {
            $this->removeFile($path);

            return null;
        }

        if (!is_object($cached)) {
            $this->removeFile($path);

            return null;
        }

        return $cached;
    }

    public function set(string $className, string $type, string $code): void
    {
        $path = $this->getCachePath($className, $type);
        $this->ensureDirectory(dirname($path));

        $tempPath = $path.'.'.bin2hex(random_bytes(6)).'.tmp';

        error_clear_last();
        if (@file_put_contents($tempPath, $code) !== strlen($code)) {
            $error = $this->lastErrorMessage();
            @unlink($tempPath);

            throw new \RuntimeException("Unable to write cache file \"{$tempPath}\": {$error}");
        }

        if (!@rename($tempPath, $path)) {
            $error = $this->lastErrorMessage();
            @unlink($tempPath);

            throw new \RuntimeException("Unable to move cache file \"{$tempPath}\" to \"{$path}\": {$error}");
        }

        $this->invalidateOpcache($path);
    }

    public function invalidate(string $className, string $type): void
    {
        $path = $this->getCachePath($className, $type);

        error_clear_last();
        if (file_exists($path) && !@unlink($path) && file_exists($path)) {
            throw new \RuntimeException("Unable to delete cache file \"{$path}\": {$this->lastErrorMessage()}");
        }

        $this->invalidateOpcache($path);
    }

    public function clear(): void
    {
        $this->deleteDirectory($this->cacheDirectory);
        $this->ensureDirectory($this->cacheDirectory);
    }

    private function getCachePath(string $className, string $type): string
    {
        $hash = md5($className);
        $safeName = str_replace('\\', '_', $className);

        return $this->cacheDirectory.DIRECTORY_SEPARATOR.$type.DIRECTORY_SEPARATOR.$safeName.'_'.$hash.'.php';
    }

    /**
     * Creates the directory, tolerating another process creating it concurrently.
     */
    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        error_clear_last();
        if (!@mkdir($directory, 0o755, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create cache directory \"{$directory}\": {$this->lastErrorMessage()}");
        }
    }

    private function removeFile(string $path): void
    {
        @unlink($path);
        $this->invalidateOpcache($path);
    }

    private function invalidateOpcache(string $path): void
    {
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }

    private function lastErrorMessage(): string
    {
        return error_get_last()['message'] ?? 'unknown error';
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = @scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                $this->removeFile($path);
            }
        }

        @rmdir($directory);
    }
}
