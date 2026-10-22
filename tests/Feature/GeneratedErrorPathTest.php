<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\Errors\Error;
use DrabekDigital\Hydra\Exceptions\DehydrationFailure;
use DrabekDigital\Hydra\Exceptions\HydrationFailure;
use DrabekDigital\Hydra\Runtime\ArrayDehydratorRuntime;
use DrabekDigital\Hydra\Runtime\ArrayHydratorRuntime;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\DiscriminatedGarageDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\ErrorPathDehydrationListDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\ErrorPathDiscriminatedDehydrationListDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\ErrorPathListDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\NoConstructorDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\SimpleDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Car;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Motorcycle;
use DrabekDigital\Hydra\Tests\TestCase;

/**
 * Error paths built by generated code must contain the actual item index,
 * e.g. `items[1].age`, never a PHP variable name such as `items[$i_items].age`.
 *
 * @internal
 *
 * @coversNothing
 */
class GeneratedErrorPathTest extends TestCase
{
    public function testListOfNestedObjectErrorPathContainsIndex(): void
    {
        $errors = $this->collectHydrationErrors(fn () => $this->hydrator->hydrate(
            ['items' => [
                ['name' => 'ok', 'age' => 1],
                ['name' => 'bad', 'age' => 'not-int'],
            ]],
            ErrorPathListDTO::class
        ));

        $this->assertErrorPaths([['age', 'items[1].age']], $errors);
    }

    public function testDiscriminatedListOfErrorPathContainsIndex(): void
    {
        $errors = $this->collectHydrationErrors(fn () => $this->hydrator->hydrate(
            [
                'featured' => ['type' => 'car', 'brand' => 'Skoda', 'year' => 2020, 'doors' => 5],
                'vehicles' => [
                    ['type' => 'motorcycle', 'brand' => 'Jawa', 'year' => 1970, 'engineCC' => 350],
                    ['type' => 'car', 'brand' => 'Tatra', 'year' => 1960, 'doors' => 'four'],
                ],
            ],
            DiscriminatedGarageDTO::class
        ));

        $this->assertErrorPaths([['doors', 'vehicles[1].doors']], $errors);
    }

    public function testSimpleArrayHydratorErrorPathWithoutPrefix(): void
    {
        $errors = $this->collectHydrationErrors(fn () => $this->hydrator->hydrateArray(
            [['name' => 'ok', 'age' => 1], ['name' => 'bad', 'age' => 'not-int']],
            className: SimpleDTO::class
        ));

        $this->assertErrorPaths([['age', '[1].age']], $errors);
    }

    public function testSimpleArrayHydratorErrorPathWithPrefix(): void
    {
        $arrayHydrator = $this->invokePrivate($this->hydrator, 'getSimpleArrayHydrator', SimpleDTO::class);
        $this->assertInstanceOf(ArrayHydratorRuntime::class, $arrayHydrator);

        $errors = $this->collectHydrationErrors(fn () => $arrayHydrator->hydrate(
            [['name' => 'ok', 'age' => 1], ['name' => 'bad', 'age' => 'not-int']],
            'prefix'
        ));

        $this->assertErrorPaths([['age', 'prefix[1].age']], $errors);
    }

    public function testSimpleArrayHydratorNonArrayItemErrorPathWithPrefix(): void
    {
        $arrayHydrator = $this->invokePrivate($this->hydrator, 'getSimpleArrayHydrator', SimpleDTO::class);
        $this->assertInstanceOf(ArrayHydratorRuntime::class, $arrayHydrator);

        $errors = $this->collectHydrationErrors(fn () => $arrayHydrator->hydrate(
            [['name' => 'ok', 'age' => 1], 'not-an-array'],
            'prefix'
        ));

        $this->assertErrorPaths([['1', 'prefix[1]']], $errors);
    }

    public function testDiscriminatedArrayHydratorErrorPathWithPrefix(): void
    {
        $arrayHydrator = $this->invokePrivate(
            $this->hydrator,
            'getDiscriminatedArrayHydrator',
            'type',
            ['car' => Car::class, 'motorcycle' => Motorcycle::class]
        );
        $this->assertInstanceOf(ArrayHydratorRuntime::class, $arrayHydrator);

        $errors = $this->collectHydrationErrors(fn () => $arrayHydrator->hydrate(
            [
                ['type' => 'motorcycle', 'brand' => 'Jawa', 'year' => 1970, 'engineCC' => 350],
                ['type' => 'car', 'brand' => 'Tatra', 'year' => 1960, 'doors' => 'four'],
            ],
            'prefix'
        ));

        $this->assertErrorPaths([['doors', 'prefix[1].doors']], $errors);
    }

    public function testListOfNestedObjectDehydrationErrorPathContainsIndex(): void
    {
        $valid = new NoConstructorDTO();
        $valid->title = 'ok';
        $valid->count = 1;

        $errors = $this->collectDehydrationErrors(fn () => $this->dehydrator->dehydrate(
            new ErrorPathDehydrationListDTO([$valid, new NoConstructorDTO()])
        ));

        $this->assertErrorPaths([['title', 'items[1].title'], ['count', 'items[1].count']], $errors);
    }

    public function testDiscriminatedListOfDehydrationErrorPathContainsIndex(): void
    {
        $valid = new NoConstructorDTO();
        $valid->title = 'ok';
        $valid->count = 1;

        $errors = $this->collectDehydrationErrors(fn () => $this->dehydrator->dehydrate(
            new ErrorPathDiscriminatedDehydrationListDTO([$valid, new NoConstructorDTO()])
        ));

        $this->assertErrorPaths([['title', 'items[1].title'], ['count', 'items[1].count']], $errors);
    }

    public function testArrayDehydratorErrorPathWithoutPrefix(): void
    {
        $valid = new NoConstructorDTO();
        $valid->title = 'ok';
        $valid->count = 1;

        $errors = $this->collectDehydrationErrors(fn () => $this->dehydrator->dehydrateArray([$valid, new NoConstructorDTO()]));

        $this->assertErrorPaths([['title', '[1].title'], ['count', '[1].count']], $errors);
    }

    public function testArrayDehydratorErrorPathWithPrefix(): void
    {
        $arrayDehydrator = $this->invokePrivate($this->dehydrator, 'getArrayDehydrator', [NoConstructorDTO::class]);
        $this->assertInstanceOf(ArrayDehydratorRuntime::class, $arrayDehydrator);

        $valid = new NoConstructorDTO();
        $valid->title = 'ok';
        $valid->count = 1;

        $errors = $this->collectDehydrationErrors(fn () => $arrayDehydrator->dehydrate([$valid, new NoConstructorDTO()], 'prefix'));

        $this->assertErrorPaths([['title', 'prefix[1].title'], ['count', 'prefix[1].count']], $errors);
    }

    private function invokePrivate(object $object, string $method, mixed ...$args): mixed
    {
        return new \ReflectionMethod($object, $method)->invoke($object, ...$args);
    }

    /**
     * @return array<Error>
     */
    private function collectHydrationErrors(callable $callback): array
    {
        try {
            $callback();
        } catch (HydrationFailure $e) {
            return $e->getErrors();
        }
        $this->fail('Expected HydrationFailure');
    }

    /**
     * @return array<Error>
     */
    private function collectDehydrationErrors(callable $callback): array
    {
        try {
            $callback();
        } catch (DehydrationFailure $e) {
            return $e->getErrors();
        }
        $this->fail('Expected DehydrationFailure');
    }

    /**
     * @param list<array{string, string}> $expected [key, fullPath] pairs
     * @param array<Error>                $errors
     */
    private function assertErrorPaths(array $expected, array $errors): void
    {
        $actual = array_map(fn (Error $error) => [(string) $error->key, $error->fullPath], array_values($errors));
        $this->assertSame($expected, $actual);
    }
}
