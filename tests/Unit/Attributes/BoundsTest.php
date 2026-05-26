<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\Attributes;

use DrabekDigital\Hydra\Attributes\Validation\DateTimeRange;
use DrabekDigital\Hydra\Attributes\Validation\NumberRange;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the isMinInclusive / isMaxInclusive helpers shared by NumberRange and DateTimeRange.
 *
 * @internal
 *
 * @coversNothing
 */
class BoundsTest extends TestCase
{
    // ── NumberRange ───────────────────────────────────────────────────

    #[DataProvider('numberBoundsProvider')]
    public function testNumberRangeBounds(string $bounds, bool $minInclusive, bool $maxInclusive): void
    {
        $rule = new NumberRange(bounds: $bounds);
        $this->assertSame($minInclusive, $rule->isMinInclusive(), "min inclusive for '{$bounds}'");
        $this->assertSame($maxInclusive, $rule->isMaxInclusive(), "max inclusive for '{$bounds}'");
    }

    /** @return array<string, array{string, bool, bool}> */
    public static function numberBoundsProvider(): array
    {
        return [
            'closed []' => ['[]', true, true],
            'open ()' => ['()', false, false],
            'left-closed [)' => ['[)', true, false],
            'right-closed (]' => ['(]', false, true],
        ];
    }

    public function testNumberRangeDefaultBoundsAreInclusive(): void
    {
        $rule = new NumberRange();
        $this->assertTrue($rule->isMinInclusive());
        $this->assertTrue($rule->isMaxInclusive());
    }

    #[DataProvider('invalidBoundsProvider')]
    public function testNumberRangeRejectsInvalidBounds(string $bounds): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid bounds notation');

        new NumberRange(bounds: $bounds);
    }

    // ── DateTimeRange ─────────────────────────────────────────────────

    #[DataProvider('dateTimeBoundsProvider')]
    public function testDateTimeRangeBounds(string $bounds, bool $minInclusive, bool $maxInclusive): void
    {
        $rule = new DateTimeRange(bounds: $bounds);
        $this->assertSame($minInclusive, $rule->isMinInclusive(), "min inclusive for '{$bounds}'");
        $this->assertSame($maxInclusive, $rule->isMaxInclusive(), "max inclusive for '{$bounds}'");
    }

    /** @return array<string, array{string, bool, bool}> */
    public static function dateTimeBoundsProvider(): array
    {
        return [
            'closed []' => ['[]', true, true],
            'open ()' => ['()', false, false],
            'left-closed [)' => ['[)', true, false],
            'right-closed (]' => ['(]', false, true],
        ];
    }

    public function testDateTimeRangeDefaultBoundsAreInclusive(): void
    {
        $rule = new DateTimeRange();
        $this->assertTrue($rule->isMinInclusive());
        $this->assertTrue($rule->isMaxInclusive());
    }

    #[DataProvider('invalidBoundsProvider')]
    public function testDateTimeRangeRejectsInvalidBounds(string $bounds): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid bounds notation');

        new DateTimeRange(bounds: $bounds);
    }

    /** @return array<string, array{string}> */
    public static function invalidBoundsProvider(): array
    {
        return [
            'empty' => [''],
            'swapped brackets' => [']['],
            'single open bracket' => ['['],
            'single close bracket' => [']'],
            'too long' => ['[]]'],
            'whitespace padded' => [' [] '],
            'nonsense' => ['xx'],
        ];
    }
}
