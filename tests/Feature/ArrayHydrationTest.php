<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\Exceptions\HydrationFailure;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\SimpleDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Car;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Motorcycle;
use DrabekDigital\Hydra\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class ArrayHydrationTest extends TestCase
{
    // =========================================================================
    // Simple Array Hydration
    // =========================================================================

    public function testHydratesSimpleArray(): void
    {
        $data = [
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
            ['name' => 'Bob', 'age' => 35],
        ];

        $this->assertArrayHydrates($data, SimpleDTO::class, 3, function (array $results) {
            $this->assertInstanceOf(SimpleDTO::class, $results[0]);
            $this->assertInstanceOf(SimpleDTO::class, $results[1]);
            $this->assertInstanceOf(SimpleDTO::class, $results[2]);
            $this->assertSame('John', $results[0]->name);
            $this->assertSame(30, $results[0]->age);
            $this->assertSame('Jane', $results[1]->name);
            $this->assertSame('Bob', $results[2]->name);
        });
    }

    public function testHydratesEmptyArray(): void
    {
        $results = $this->hydrator->hydrateArray([], className: SimpleDTO::class);
        $this->assertSame([], $results);
    }

    public function testHydratesSingleItemArray(): void
    {
        $data = [
            ['name' => 'John', 'age' => 30],
        ];

        $this->assertArrayHydrates($data, SimpleDTO::class, 1);
    }

    // =========================================================================
    // Discriminated Array Hydration
    // =========================================================================

    public function testHydratesDiscriminatedArray(): void
    {
        $data = [
            ['type' => 'car', 'brand' => 'Toyota', 'year' => 2023, 'doors' => 4],
            ['type' => 'motorcycle', 'brand' => 'Harley', 'year' => 2022, 'engineCC' => 1200],
            ['type' => 'car', 'brand' => 'Ford', 'year' => 2021, 'doors' => 2],
        ];

        $this->assertDiscriminatedArrayHydrates(
            $data,
            'type',
            ['car' => Car::class, 'motorcycle' => Motorcycle::class],
            function (array $results) {
                $this->assertCount(3, $results);

                $this->assertInstanceOf(Car::class, $results[0]);
                $this->assertSame('Toyota', $results[0]->brand);
                $this->assertSame(4, $results[0]->doors);

                $this->assertInstanceOf(Motorcycle::class, $results[1]);
                $this->assertSame('Harley', $results[1]->brand);
                $this->assertSame(1200, $results[1]->engineCC);

                $this->assertInstanceOf(Car::class, $results[2]);
                $this->assertSame('Ford', $results[2]->brand);
            }
        );
    }

    public function testFailsOnMissingDiscriminator(): void
    {
        $data = [
            ['type' => 'car', 'brand' => 'Toyota', 'year' => 2023, 'doors' => 4],
            ['brand' => 'Harley', 'year' => 2022, 'engineCC' => 1200], // missing type
        ];

        $this->expectException(HydrationFailure::class);

        $this->hydrator->hydrateArray(
            $data,
            discriminator: 'type',
            mapping: ['car' => Car::class, 'motorcycle' => Motorcycle::class]
        );
    }

    public function testFailsOnUnknownDiscriminatorValue(): void
    {
        $data = [
            ['type' => 'car', 'brand' => 'Toyota', 'year' => 2023, 'doors' => 4],
            ['type' => 'boat', 'brand' => 'Yamaha', 'year' => 2022], // unknown type
        ];

        $this->expectException(HydrationFailure::class);

        $this->hydrator->hydrateArray(
            $data,
            discriminator: 'type',
            mapping: ['car' => Car::class, 'motorcycle' => Motorcycle::class]
        );
    }

    // =========================================================================
    // Error Handling
    // =========================================================================

    public function testFailsOnNonArrayItem(): void
    {
        $data = [
            ['name' => 'John', 'age' => 30],
            'not-an-array',
            ['name' => 'Bob', 'age' => 35],
        ];

        $this->expectException(HydrationFailure::class);
        $this->hydrator->hydrateArray($data, className: SimpleDTO::class);
    }

    public function testFailsOnMissingKeyInItem(): void
    {
        $data = [
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane'], // missing age
        ];

        $this->expectException(HydrationFailure::class);
        $this->hydrator->hydrateArray($data, className: SimpleDTO::class);
    }

    public function testAggregatesErrorsOfAllInvalidItems(): void
    {
        $data = [
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane'],
            ['name' => 'Bob', 'age' => 35],
            ['name' => 42, 'age' => 40],
        ];

        $this->assertSame([
            'missing_key|age|[1].age',
            'type_mismatch|name|[3].name',
        ], $this->collectArrayHydrationErrors(fn () => $this->hydrator->hydrateArray($data, className: SimpleDTO::class)));
    }

    public function testAggregatesNonArrayItemWithInvalidItems(): void
    {
        $data = [
            ['name' => 'Jane'],
            'not-an-array',
            ['name' => 'Bob', 'age' => 35],
            ['age' => 'old'],
        ];

        $this->assertSame([
            'missing_key|age|[0].age',
            'type_mismatch|1|[1]',
            'missing_key|name|[3].name',
            'type_mismatch|age|[3].age',
        ], $this->collectArrayHydrationErrors(fn () => $this->hydrator->hydrateArray($data, className: SimpleDTO::class)));
    }

    public function testAggregatesErrorsOfAllInvalidDiscriminatedItems(): void
    {
        $data = [
            ['type' => 'car', 'brand' => 'Toyota', 'year' => 2023, 'doors' => 4],
            ['type' => 'car', 'brand' => 'Ford', 'year' => 2021],
            ['type' => 'boat', 'brand' => 'Yamaha', 'year' => 2022],
            ['type' => 'motorcycle', 'brand' => 'Harley', 'year' => 'old', 'engineCC' => 1200],
            ['brand' => 'Unknown'],
            'not-an-array',
        ];

        $this->assertSame([
            'missing_key|doors|[1].doors',
            'invalid_value|type|[2]',
            'type_mismatch|year|[3].year',
            'missing_key|type|[4]',
            'type_mismatch|5|[5]',
        ], $this->collectArrayHydrationErrors(fn () => $this->hydrator->hydrateArray(
            $data,
            discriminator: 'type',
            mapping: ['car' => Car::class, 'motorcycle' => Motorcycle::class]
        )));
    }

    public function testRequiresEitherClassnameOrDiscriminator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->hydrator->hydrateArray([]);
    }

    // =========================================================================
    // Array Dehydration
    // =========================================================================

    public function testDehydratesArrayOfSameType(): void
    {
        $objects = [
            new SimpleDTO('John', 30),
            new SimpleDTO('Jane', 25),
        ];

        $result = $this->dehydrator->dehydrateArray($objects);

        $this->assertEquals([
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
        ], $result);
    }

    public function testDehydratesEmptyArray(): void
    {
        $result = $this->dehydrator->dehydrateArray([]);
        $this->assertSame([], $result);
    }

    public function testDehydratesMixedTypes(): void
    {
        $objects = [
            new Car('Toyota', 2023, 4),
            new Motorcycle('Harley', 2022, 1200),
            new Car('Ford', 2021, 2),
        ];

        $result = $this->dehydrator->dehydrateArray($objects);

        $this->assertCount(3, $result);
        $this->assertSame('Toyota', $result[0]['brand']);
        $this->assertSame(4, $result[0]['doors']);
        $this->assertSame('Harley', $result[1]['brand']);
        $this->assertSame(1200, $result[1]['engineCC']);
    }

    // =========================================================================
    // Roundtrip
    // =========================================================================

    public function testRoundtripSimpleArray(): void
    {
        $original = [
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
        ];

        $objects = $this->hydrator->hydrateArray($original, className: SimpleDTO::class);
        $result = $this->dehydrator->dehydrateArray($objects);

        $this->assertEquals($original, $result);
    }

    /**
     * @return list<string> errors formatted as "code|key|fullPath"
     */
    private function collectArrayHydrationErrors(callable $hydrate): array
    {
        try {
            $hydrate();
            $this->fail('Expected HydrationFailure was not thrown');
        } catch (HydrationFailure $e) {
            return array_values(array_map(
                fn ($error) => "{$error->code->value}|{$error->key}|{$error->fullPath}",
                $e->getErrors()
            ));
        }
    }
}
