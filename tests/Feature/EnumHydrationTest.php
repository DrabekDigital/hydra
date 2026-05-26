<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\Tests\Fixtures\Dto\EnumDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\EnumUndefinableDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\EnumWithDefaultDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Enums\Priority;
use DrabekDigital\Hydra\Tests\Fixtures\Enums\Status;
use DrabekDigital\Hydra\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class EnumHydrationTest extends TestCase
{
    public function testHydratesStringBackedEnum(): void
    {
        $this->assertHydrates(
            ['status' => 'published', 'priority' => 2],
            EnumDTO::class,
            fn (EnumDTO $dto) => $this->assertSame(Status::Published, $dto->status)
        );
    }

    public function testHydratesIntBackedEnum(): void
    {
        $this->assertHydrates(
            ['status' => 'draft', 'priority' => 3],
            EnumDTO::class,
            fn (EnumDTO $dto) => $this->assertSame(Priority::High, $dto->priority)
        );
    }

    public function testUsesDefaultEnumValue(): void
    {
        $this->assertHydrates(
            ['name' => 'Test'],
            EnumWithDefaultDTO::class,
            fn (EnumWithDefaultDTO $dto) => $this->assertSame(Status::Draft, $dto->status)
        );
    }

    public function testProvidedEnumOverridesDefault(): void
    {
        $this->assertHydrates(
            ['name' => 'Test', 'status' => 'archived'],
            EnumWithDefaultDTO::class,
            fn (EnumWithDefaultDTO $dto) => $this->assertSame(Status::Archived, $dto->status)
        );
    }

    public function testUndefinableEnumRemainsUninitialized(): void
    {
        $dto = $this->hydrator->hydrate(['name' => 'Test'], EnumUndefinableDTO::class);

        $this->assertSame('Test', $dto->name);

        $reflection = new \ReflectionProperty($dto, 'status');
        $this->assertFalse($reflection->isInitialized($dto));
    }

    public function testUndefinableEnumAcceptsValue(): void
    {
        $dto = $this->hydrator->hydrate(
            ['name' => 'Test', 'status' => 'published'],
            EnumUndefinableDTO::class
        );

        $this->assertSame(Status::Published, $dto->status);
    }

    public function testFailsOnInvalidEnumValue(): void
    {
        $this->assertHydrationFails(
            ['status' => 'invalid', 'priority' => 1],
            EnumDTO::class,
            'invalid_value',
            'status'
        );
    }

    public function testFailsOnWrongTypeForEnum(): void
    {
        $this->assertHydrationFails(
            ['status' => 123, 'priority' => 1], // status should be string
            EnumDTO::class,
            'type_mismatch',
            'status'
        );
    }
}
