<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\CodeGen;

/**
 * Version of the generated runtime code (hydrators, dehydrators and their array variants).
 *
 * The version is part of every cache key used by `Hydrator` and `Dehydrator`, so code generated
 * by an older Hydra release is never loaded from a persistent cache after an upgrade.
 */
final class GeneratorVersion
{
    /**
     * MUST be bumped whenever generated code changes (CodeGen, TypeHandlers, HydraAst, runtime contracts),
     * otherwise persistent caches keep executing stale generated code after an upgrade.
     */
    public const string GENERATOR_VERSION = '1';

    private const string VALID_VERSION_PATTERN = '/^[A-Za-z0-9.\-]+$/';

    private function __construct() {}

    /**
     * Prefix a cache key with the generator version.
     */
    public static function cacheKey(string $key, string $version = self::GENERATOR_VERSION): string
    {
        return "v{$version}_{$key}";
    }

    /**
     * @throws \InvalidArgumentException When the version contains characters unsafe for cache keys/file names
     */
    public static function assertValid(string $version): void
    {
        if (preg_match(self::VALID_VERSION_PATTERN, $version) !== 1) {
            throw new \InvalidArgumentException(
                "Invalid generator version '{$version}': only letters, digits, '.' and '-' are allowed"
            );
        }
    }
}
