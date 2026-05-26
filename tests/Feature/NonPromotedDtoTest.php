<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\Tests\Fixtures\Dto\LegacyStyleDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\MutableDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\NestedMutableDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\NoConstructorDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\NonPromotedDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\PublicPropertiesDTO;
use DrabekDigital\Hydra\Tests\TestCase;

/**
 * Tests for DTOs that don't use constructor promotion and/or readonly properties.
 *
 * @internal
 *
 * @coversNothing
 */
class NonPromotedDtoTest extends TestCase
{
    public function testHydratesNonPromotedDto(): void
    {
        $this->assertHydrates(
            ['name' => 'John', 'age' => 30],
            NonPromotedDTO::class,
            function (NonPromotedDTO $dto) {
                $this->assertSame('John', $dto->name);
                $this->assertSame(30, $dto->age);
            }
        );
    }

    public function testDehydratesNonPromotedDto(): void
    {
        $dto = new NonPromotedDTO('Jane', 25);

        $this->assertDehydrates($dto, [
            'name' => 'Jane',
            'age' => 25,
        ]);
    }

    public function testRoundtripNonPromotedDto(): void
    {
        $this->assertRoundtrip(
            ['name' => 'Alice', 'age' => 42],
            NonPromotedDTO::class
        );
    }

    public function testHydratesMutableDto(): void
    {
        $this->assertHydrates(
            ['name' => 'Test', 'count' => 5],
            MutableDTO::class,
            function (MutableDTO $dto) {
                $this->assertSame('Test', $dto->name);
                $this->assertSame(5, $dto->count);

                // Verify mutability
                $dto->name = 'Modified';
                $dto->count = 10;
                $this->assertSame('Modified', $dto->name);
                $this->assertSame(10, $dto->count);
            }
        );
    }

    public function testDehydratesMutableDto(): void
    {
        $dto = new MutableDTO('Original', 1);
        $dto->name = 'Changed';
        $dto->count = 99;

        $this->assertDehydrates($dto, [
            'name' => 'Changed',
            'count' => 99,
        ]);
    }

    public function testHydratesPublicPropertiesWithAllFeatures(): void
    {
        // All values provided
        $this->assertHydrates(
            [
                'required' => 'req',
                'nullable' => 'nullable_val',
                'withDefault' => 'custom',
                'undefinable' => 'defined',
            ],
            PublicPropertiesDTO::class,
            function (PublicPropertiesDTO $dto) {
                $this->assertSame('req', $dto->required);
                $this->assertSame('nullable_val', $dto->nullable);
                $this->assertSame('custom', $dto->withDefault);
                $this->assertSame('defined', $dto->undefinable);
            }
        );
    }

    public function testHydratesPublicPropertiesWithDefaults(): void
    {
        // Only required, nullable as null
        $this->assertHydrates(
            [
                'required' => 'req',
                'nullable' => null,
            ],
            PublicPropertiesDTO::class,
            function (PublicPropertiesDTO $dto) {
                $this->assertSame('req', $dto->required);
                $this->assertNull($dto->nullable);
                $this->assertSame('default_value', $dto->withDefault);
                $this->assertFalse(isset($dto->undefinable) && new \ReflectionProperty($dto, 'undefinable')->isInitialized($dto));
            }
        );
    }

    public function testHydratesLegacyStyleDto(): void
    {
        $this->assertHydrates(
            ['title' => 'My Title', 'description' => 'A description'],
            LegacyStyleDTO::class,
            function (LegacyStyleDTO $dto) {
                $this->assertSame('My Title', $dto->title);
                $this->assertSame('A description', $dto->description);
                $this->assertSame(0, $dto->priority);
                $this->assertTrue($dto->active);
            }
        );
    }

    public function testHydratesLegacyStyleDtoWithAllValues(): void
    {
        $this->assertHydrates(
            [
                'title' => 'Task',
                'description' => 'Do something',
                'priority' => 5,
                'active' => false,
            ],
            LegacyStyleDTO::class,
            function (LegacyStyleDTO $dto) {
                $this->assertSame('Task', $dto->title);
                $this->assertSame('Do something', $dto->description);
                $this->assertSame(5, $dto->priority);
                $this->assertFalse($dto->active);
            }
        );
    }

    public function testDehydratesLegacyStyleDto(): void
    {
        $dto = new LegacyStyleDTO('Title', 'Desc', 3, false);

        $this->assertDehydrates($dto, [
            'title' => 'Title',
            'description' => 'Desc',
            'priority' => 3,
            'active' => false,
        ]);
    }

    public function testRoundtripLegacyStyleDto(): void
    {
        $this->assertRoundtrip(
            [
                'title' => 'Round Trip',
                'description' => 'Testing',
                'priority' => 7,
                'active' => true,
            ],
            LegacyStyleDTO::class
        );
    }

    public function testHydratesNestedMutableDto(): void
    {
        $this->assertHydrates(
            [
                'name' => 'Parent',
                'child' => ['name' => 'Child', 'count' => 42],
            ],
            NestedMutableDTO::class,
            function (NestedMutableDTO $dto) {
                $this->assertSame('Parent', $dto->name);
                $this->assertInstanceOf(MutableDTO::class, $dto->child);
                $this->assertSame('Child', $dto->child->name);
                $this->assertSame(42, $dto->child->count);
            }
        );
    }

    public function testDehydratesNestedMutableDto(): void
    {
        $child = new MutableDTO('Inner', 10);
        $dto = new NestedMutableDTO('Outer', $child);

        $this->assertDehydrates($dto, [
            'name' => 'Outer',
            'child' => [
                'name' => 'Inner',
                'count' => 10,
            ],
        ]);
    }

    public function testRoundtripNestedMutableDto(): void
    {
        $this->assertRoundtrip(
            [
                'name' => 'Parent',
                'child' => ['name' => 'Child', 'count' => 100],
            ],
            NestedMutableDTO::class
        );
    }

    public function testMutatedDtoDehydratesCorrectly(): void
    {
        // Hydrate, mutate, dehydrate
        $dto = $this->hydrator->hydrate(['name' => 'Original', 'count' => 1], MutableDTO::class);

        // Mutate
        $dto->name = 'Mutated';
        $dto->count = 999;

        $result = $this->dehydrator->dehydrate($dto);

        $this->assertSame([
            'name' => 'Mutated',
            'count' => 999,
        ], $result);
    }

    // =========================================================================
    // No Constructor DTO Tests
    // =========================================================================

    public function testHydratesNoConstructorDtoWithAllValues(): void
    {
        $this->assertHydrates(
            [
                'title' => 'Test Title',
                'count' => 42,
                'description' => 'A description',
                'active' => false,
            ],
            NoConstructorDTO::class,
            function (NoConstructorDTO $dto) {
                $this->assertSame('Test Title', $dto->title);
                $this->assertSame(42, $dto->count);
                $this->assertSame('A description', $dto->description);
                $this->assertFalse($dto->active);
            }
        );
    }

    public function testHydratesNoConstructorDtoWithDefaults(): void
    {
        $this->assertHydrates(
            [
                'title' => 'Only Required',
                'count' => 10,
            ],
            NoConstructorDTO::class,
            function (NoConstructorDTO $dto) {
                $this->assertSame('Only Required', $dto->title);
                $this->assertSame(10, $dto->count);
                $this->assertNull($dto->description); // default
                $this->assertTrue($dto->active); // default
            }
        );
    }

    public function testDehydratesNoConstructorDto(): void
    {
        $dto = new NoConstructorDTO();
        $dto->title = 'Manual Title';
        $dto->count = 100;
        $dto->description = 'Set manually';
        $dto->active = false;

        $this->assertDehydrates($dto, [
            'title' => 'Manual Title',
            'count' => 100,
            'description' => 'Set manually',
            'active' => false,
        ]);
    }

    public function testRoundtripNoConstructorDto(): void
    {
        $this->assertRoundtrip(
            [
                'title' => 'Round Trip',
                'count' => 55,
                'description' => 'Testing round trip',
                'active' => true,
            ],
            NoConstructorDTO::class
        );
    }

    public function testNoConstructorDtoMissingRequiredFails(): void
    {
        $this->assertHydrationFails(
            ['title' => 'Missing count'],
            NoConstructorDTO::class,
            'missing_key',
            'count'
        );
    }
}
