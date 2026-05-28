<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\Attributes;

use DrabekDigital\Hydra\Attributes\Validation\Count;
use DrabekDigital\Hydra\Attributes\Validation\DateTimeRange;
use DrabekDigital\Hydra\Attributes\Validation\Ip;
use DrabekDigital\Hydra\Attributes\Validation\LengthRange;
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

    // ── Crossed / empty intervals ─────────────────────────────────────

    public function testCountRejectsCrossedRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('min (5) must not be greater than max (1)');

        new Count(min: 5, max: 1);
    }

    public function testLengthRangeRejectsCrossedRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LengthRange(min: 10, max: 2);
    }

    public function testNumberRangeRejectsCrossedRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('min must not be greater than max');

        new NumberRange(min: 5, max: 3);
    }

    public function testDateTimeRangeRejectsCrossedRange(): void
    {
        $min = new \DateTimeImmutable('2025-01-01');
        $max = new \DateTimeImmutable('2024-01-01');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('min must not be greater than max');

        new DateTimeRange(min: $min, max: $max);
    }

    #[DataProvider('emptyIntervalBoundsProvider')]
    public function testNumberRangeRejectsEmptyIntervalWhenMinEqualsMax(string $bounds): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('empty interval');

        new NumberRange(min: 5, max: 5, bounds: $bounds);
    }

    /** @return array<string, array{string}> */
    public static function emptyIntervalBoundsProvider(): array
    {
        return [
            'open ()' => ['()'],
            'left-closed [)' => ['[)'],
            'right-closed (]' => ['(]'],
        ];
    }

    public function testNumberRangeAllowsSinglePointWithInclusiveBounds(): void
    {
        $rule = new NumberRange(min: 5, max: 5, bounds: '[]');
        $this->assertSame(5, $rule->min);
        $this->assertSame(5, $rule->max);
    }

    public function testIpRejectsWhenNeitherVersionAllowed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one of IPv4 or IPv6');

        new Ip(allowV4: false, allowV6: false);
    }
}
