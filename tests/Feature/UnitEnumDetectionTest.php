<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class UnitEnumDetectionTest extends TestCase
{
    public function testUnitEnumAsPropertyThrowsLogicExceptionOnHydration(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Unit enum.*UnitColor.*cannot be used/');

        $this->hydrator->hydrate(['color' => 'Red'], UnitEnumPropertyDTO::class);
    }

    public function testUnitEnumAsPropertyThrowsLogicExceptionOnDehydration(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/Unit enum.*UnitColor.*cannot be used/');

        // Use reflection to bypass constructor to create an object with a unit enum property
        $dto = new \ReflectionClass(UnitEnumPropertyDTO::class)->newInstanceWithoutConstructor();
        $this->dehydrator->dehydrate($dto);
    }

    public function testBackedEnumStillWorksNormally(): void
    {
        // Regression: backed enums must not be affected
        $this->assertHydrates(
            ['status' => 'active', 'name' => 'test'],
            BackedEnumPropertyDTO::class,
            function (BackedEnumPropertyDTO $dto) {
                $this->assertSame(BackedStatus::Active, $dto->status);
            }
        );
    }
}

enum UnitColor
{
    case Red;
    case Blue;
}

enum BackedStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

final class UnitEnumPropertyDTO
{
    public function __construct(
        public readonly UnitColor $color,
    ) {}
}

final class BackedEnumPropertyDTO
{
    public function __construct(
        public readonly BackedStatus $status,
        public readonly string $name,
    ) {}
}
