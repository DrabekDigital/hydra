<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\Exceptions\HydrationFailure;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\DiscriminatedGarageDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\DiscriminatedGaragePassThroughDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\DiscriminatedGaragePassThroughSuccessDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Car;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\Motorcycle;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\TypedCar;
use DrabekDigital\Hydra\Tests\Fixtures\Objects\TypedMotorcycle;
use DrabekDigital\Hydra\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class DiscriminatorAttributesTest extends TestCase
{
    public function testObjectTypeAndListOfHydrateDiscriminatedSubtypes(): void
    {
        $dto = $this->hydrator->hydrate([
            'featured' => ['type' => 'car', 'brand' => 'Skoda', 'year' => 2024, 'doors' => 5],
            'vehicles' => [
                ['type' => 'car', 'brand' => 'Toyota', 'year' => 2020, 'doors' => 4],
                ['type' => 'motorcycle', 'brand' => 'Honda', 'year' => 2019, 'engineCC' => 750],
            ],
        ], DiscriminatedGarageDTO::class);

        $this->assertInstanceOf(Car::class, $dto->featured);
        $this->assertInstanceOf(Car::class, $dto->vehicles[0]);
        $this->assertInstanceOf(Motorcycle::class, $dto->vehicles[1]);
    }

    public function testObjectTypeAndListOfDehydrateAddsDiscriminatorKey(): void
    {
        $dto = new DiscriminatedGarageDTO(
            featured: new Car('Skoda', 2024, 5),
            vehicles: [
                new Car('Toyota', 2020, 4),
                new Motorcycle('Honda', 2019, 750),
            ],
        );

        $result = $this->dehydrator->dehydrate($dto);

        $this->assertIsArray($result['featured']);
        $this->assertIsArray($result['vehicles']);
        $this->assertIsArray($result['vehicles'][0]);
        $this->assertIsArray($result['vehicles'][1]);
        $this->assertSame('car', $result['featured']['type']);
        $this->assertSame('car', $result['vehicles'][0]['type']);
        $this->assertSame('motorcycle', $result['vehicles'][1]['type']);
    }

    public function testListOfPassDiscriminatorTrueKeepsDiscriminatorInPayload(): void
    {
        $this->expectException(HydrationFailure::class);

        $this->hydrator->hydrate([
            'vehicles' => [
                ['type' => 'car', 'brand' => 'Toyota', 'year' => 2020, 'doors' => 4],
            ],
        ], DiscriminatedGaragePassThroughDTO::class);
    }

    public function testListOfPassDiscriminatorTrueHydratesWhenConstructorsAcceptType(): void
    {
        $dto = $this->hydrator->hydrate([
            'vehicles' => [
                ['type' => 'car', 'brand' => 'Toyota', 'year' => 2020, 'doors' => 4],
                ['type' => 'motorcycle', 'brand' => 'Honda', 'year' => 2019, 'engineCC' => 750],
            ],
        ], DiscriminatedGaragePassThroughSuccessDTO::class);

        $this->assertCount(2, $dto->vehicles);
        $this->assertInstanceOf(TypedCar::class, $dto->vehicles[0]);
        $this->assertInstanceOf(TypedMotorcycle::class, $dto->vehicles[1]);
        $this->assertSame('car', $dto->vehicles[0]->type);
        $this->assertSame('motorcycle', $dto->vehicles[1]->type);
    }
}
