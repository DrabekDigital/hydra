<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\Tests\Fixtures\Dto\DateTimeDTO;
use DrabekDigital\Hydra\Tests\Fixtures\Dto\DateTimeWithTimezoneDTO;
use DrabekDigital\Hydra\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class DateTimeHydrationTest extends TestCase
{
    public function testHydratesDatetimeWithFormat(): void
    {
        $this->assertHydrates(
            ['name' => 'Event', 'date' => '2024-06-15'],
            DateTimeDTO::class,
            function (DateTimeDTO $dto) {
                $this->assertInstanceOf(\DateTimeImmutable::class, $dto->date);
                $this->assertSame('2024-06-15', $dto->date->format('Y-m-d'));
            }
        );
    }

    public function testHydratesDatetimeWithTimezone(): void
    {
        $this->assertHydrates(
            ['timestamp' => '2024-06-15 14:30:00'],
            DateTimeWithTimezoneDTO::class,
            function (DateTimeWithTimezoneDTO $dto) {
                $this->assertInstanceOf(\DateTimeImmutable::class, $dto->timestamp);
                $this->assertSame('UTC', $dto->timestamp->getTimezone()->getName());
                $this->assertSame('2024-06-15 14:30:00', $dto->timestamp->format('Y-m-d H:i:s'));
            }
        );
    }

    public function testFailsOnInvalidDatetimeFormat(): void
    {
        $this->assertHydrationFails(
            ['name' => 'Event', 'date' => '15/06/2024'], // wrong format
            DateTimeDTO::class,
            'invalid_value',
            'date'
        );
    }

    public function testFailsOnNonStringDatetime(): void
    {
        $this->assertHydrationFails(
            ['name' => 'Event', 'date' => 12345],
            DateTimeDTO::class,
            'type_mismatch',
            'date'
        );
    }
}
