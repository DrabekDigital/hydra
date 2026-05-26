<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class DateTimeZoneHydrationTest extends TestCase
{
    public function testHydratesDateTimeZone(): void
    {
        $this->assertHydrates(
            ['tz' => 'Europe/Prague', 'label' => 'test'],
            DateTimeZoneDTO::class,
            function (DateTimeZoneDTO $dto) {
                $this->assertInstanceOf(\DateTimeZone::class, $dto->tz);
                $this->assertSame('Europe/Prague', $dto->tz->getName());
            }
        );
    }

    public function testDehydratesDateTimeZone(): void
    {
        $dto = new DateTimeZoneDTO(new \DateTimeZone('America/New_York'), 'test');
        $this->assertDehydrates($dto, ['tz' => 'America/New_York', 'label' => 'test']);
    }

    public function testRoundtrip(): void
    {
        $this->assertRoundtrip(['tz' => 'UTC', 'label' => 'utc'], DateTimeZoneDTO::class);
    }

    public function testMissingKeyProducesError(): void
    {
        $this->assertHydrationFails(['label' => 'test'], DateTimeZoneDTO::class, 'missing_key', 'tz');
    }

    public function testNonStringProducesTypeMismatch(): void
    {
        $this->assertHydrationFails(['tz' => 123, 'label' => 'test'], DateTimeZoneDTO::class, 'type_mismatch', 'tz');
    }

    public function testInvalidTimezoneProducesInvalidValue(): void
    {
        $this->assertHydrationFails(['tz' => 'Not/ATimezone', 'label' => 'test'], DateTimeZoneDTO::class, 'invalid_value', 'tz');
    }

    public function testNullableDateTimeZone(): void
    {
        $this->assertHydrates(
            ['tz' => null, 'label' => 'test'],
            NullableDateTimeZoneDTO::class,
            fn (NullableDateTimeZoneDTO $dto) => $this->assertNull($dto->tz)
        );
    }

    public function testNullableDateTimeZoneWithValue(): void
    {
        $this->assertHydrates(
            ['tz' => 'Asia/Tokyo', 'label' => 'test'],
            NullableDateTimeZoneDTO::class,
            function (NullableDateTimeZoneDTO $dto) {
                $this->assertInstanceOf(\DateTimeZone::class, $dto->tz);
                $this->assertSame('Asia/Tokyo', $dto->tz->getName());
            }
        );
    }

    public function testNullableDehydratesNull(): void
    {
        $dto = new NullableDateTimeZoneDTO(null, 'test');
        $this->assertDehydrates($dto, ['tz' => null, 'label' => 'test']);
    }
}

final class DateTimeZoneDTO
{
    public function __construct(
        public readonly \DateTimeZone $tz,
        public readonly string $label,
    ) {}
}

final class NullableDateTimeZoneDTO
{
    public function __construct(
        public readonly ?\DateTimeZone $tz,
        public readonly string $label,
    ) {}
}
