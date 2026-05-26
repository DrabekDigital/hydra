<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests;

use DrabekDigital\Hydra\Caching\FileCache;
use DrabekDigital\Hydra\Dehydrator;
use DrabekDigital\Hydra\Exceptions\DehydrationFailure;
use DrabekDigital\Hydra\Exceptions\HydrationFailure;
use DrabekDigital\Hydra\Hydrator;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected static string $cacheDir;
    protected Hydrator $hydrator;
    protected Dehydrator $dehydrator;
    protected FileCache $cache;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$cacheDir = getenv('HYDRA_TEST_CACHE_DIR') ?: __DIR__.'/.cache';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearCache();
        $this->cache = new FileCache(self::$cacheDir);
        $this->hydrator = new Hydrator($this->cache);
        $this->dehydrator = new Dehydrator($this->cache);
    }

    protected function tearDown(): void
    {
        $this->clearCache();
        parent::tearDown();
    }

    protected function clearCache(): void
    {
        if (is_dir(self::$cacheDir)) {
            $this->deleteDirectory(self::$cacheDir);
        }
    }

    // =========================================================================
    // Fluent Assertion Helpers
    // =========================================================================

    /**
     * Assert hydration produces expected object.
     *
     * @template T of object
     *
     * @param array<string, mixed> $data
     * @param class-string<T>      $class
     * @param callable(T):void     $assertions
     */
    protected function assertHydrates(array $data, string $class, callable $assertions): void
    {
        $result = $this->hydrator->hydrate($data, $class);
        $this->assertInstanceOf($class, $result);
        $assertions($result);
    }

    /**
     * Assert dehydration produces expected array.
     *
     * @param array<string, mixed> $expected
     */
    protected function assertDehydrates(object $object, array $expected): void
    {
        $result = $this->dehydrator->dehydrate($object);
        $this->assertEquals($expected, $result);
    }

    /**
     * Assert roundtrip: hydrate then dehydrate returns original data.
     *
     * @template T of object
     *
     * @param array<string, mixed> $data
     * @param class-string<T>      $class
     */
    protected function assertRoundtrip(array $data, string $class): void
    {
        $object = $this->hydrator->hydrate($data, $class);
        $result = $this->dehydrator->dehydrate($object);
        $this->assertEquals($data, $result, 'Roundtrip failed: dehydrated data does not match input');
    }

    /**
     * Assert hydration fails with specific error code.
     *
     * @template T of object
     *
     * @param array<string, mixed> $data
     * @param class-string<T>      $class
     */
    protected function assertHydrationFails(
        array $data,
        string $class,
        string $expectedErrorCode,
        ?string $expectedKey = null
    ): void {
        try {
            $this->hydrator->hydrate($data, $class);
            $this->fail('Expected HydrationFailure was not thrown');
        } catch (HydrationFailure $e) {
            $errors = $e->getErrors();
            $this->assertNotEmpty($errors, 'No errors in HydrationFailure');

            $found = false;
            foreach ($errors as $error) {
                if ($error->code->value === $expectedErrorCode) {
                    if ($expectedKey === null || $error->key === $expectedKey) {
                        $found = true;

                        break;
                    }
                }
            }

            $this->assertTrue($found, sprintf(
                'Expected error code "%s"%s not found. Got: %s',
                $expectedErrorCode,
                $expectedKey ? " for key \"{$expectedKey}\"" : '',
                implode(', ', array_map(fn ($e) => "{$e->code->value}:{$e->key}", $errors))
            ));
        }
    }

    /**
     * Assert dehydration fails with specific error code.
     */
    protected function assertDehydrationFails(
        object $object,
        string $expectedErrorCode,
        ?string $expectedKey = null
    ): void {
        try {
            $this->dehydrator->dehydrate($object);
            $this->fail('Expected DehydrationFailure was not thrown');
        } catch (DehydrationFailure $e) {
            $errors = $e->getErrors();
            $this->assertNotEmpty($errors, 'No errors in DehydrationFailure');

            $found = false;
            foreach ($errors as $error) {
                if ($error->code->value === $expectedErrorCode) {
                    if ($expectedKey === null || $error->key === $expectedKey) {
                        $found = true;

                        break;
                    }
                }
            }

            $this->assertTrue($found, sprintf(
                'Expected error code "%s"%s not found. Got: %s',
                $expectedErrorCode,
                $expectedKey ? " for key \"{$expectedKey}\"" : '',
                implode(', ', array_map(fn ($e) => "{$e->code->value}:{$e->key}", $errors))
            ));
        }
    }

    /**
     * Assert array hydration works correctly.
     *
     * @param array<int, mixed>                 $data
     * @param class-string                      $class
     * @param null|callable(array<object>):void $assertions
     */
    protected function assertArrayHydrates(
        array $data,
        string $class,
        int $expectedCount,
        ?callable $assertions = null
    ): void {
        $results = $this->hydrator->hydrateArray($data, className: $class);
        $this->assertCount($expectedCount, $results);
        foreach ($results as $result) {
            $this->assertInstanceOf($class, $result);
        }
        if ($assertions) {
            $assertions($results);
        }
    }

    /**
     * Assert discriminated array hydration.
     *
     * @param array<int, mixed>            $data
     * @param array<string, class-string>  $mapping
     * @param callable(array<object>):void $assertions
     */
    protected function assertDiscriminatedArrayHydrates(
        array $data,
        string $discriminator,
        array $mapping,
        callable $assertions
    ): void {
        $results = $this->hydrator->hydrateArray(
            $data,
            discriminator: $discriminator,
            mapping: $mapping
        );
        $assertions($results);
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } elseif (file_exists($path)) {
                unlink($path);
            }
        }
        if (is_dir($dir)) {
            rmdir($dir);
        }
    }
}
