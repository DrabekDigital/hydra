<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\Tests\Fixtures\Dto\ProductDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Mappers\Money;
use DrabekDigital\Hydra\Tests\Fixtures\Mappers\MoneyMapper;
use DrabekDigital\Hydra\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class CustomMapperTest extends TestCase
{
    public function testHydratesWithCustomMapperAttribute(): void
    {
        $this->assertHydrates(
            ['name' => 'Widget', 'price' => 1999],
            ProductDTO::class,
            function (ProductDTO $dto) {
                $this->assertSame('Widget', $dto->name);
                $this->assertInstanceOf(Money::class, $dto->price);
                $this->assertSame(1999, $dto->price->cents);
            }
        );
    }

    public function testDehydratesWithCustomMapperAttribute(): void
    {
        $dto = new ProductDTO('Widget', new Money(1999));

        $this->assertDehydrates($dto, [
            'name' => 'Widget',
            'price' => 1999,
        ]);
    }

    public function testRoundtripWithCustomMapper(): void
    {
        $original = ['name' => 'Widget', 'price' => 1999];

        $object = $this->hydrator->hydrate($original, ProductDTO::class);
        $result = $this->dehydrator->dehydrate($object);

        $this->assertEquals($original, $result);
    }

    public function testCanPreregisterMapperInstance(): void
    {
        $customMapper = new MoneyMapper();

        $this->hydrator->registerMapper(MoneyMapper::class, $customMapper);
        $this->dehydrator->registerMapper(MoneyMapper::class, $customMapper);

        $this->assertHydrates(
            ['name' => 'Widget', 'price' => 2500],
            ProductDTO::class,
            fn (ProductDTO $dto) => $this->assertSame(2500, $dto->price->cents)
        );
    }
}
