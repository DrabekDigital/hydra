<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\Validators;

use DrabekDigital\Hydra\Attributes\Validation\DateTimeRange;
use DrabekDigital\Hydra\Validators\Rules\DateTimeRangeRule;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class DateTimeRangeRuleTest extends TestCase
{
    private \DateTimeImmutable $past;
    private \DateTimeImmutable $now;
    private \DateTimeImmutable $future;

    protected function setUp(): void
    {
        $this->past = new \DateTimeImmutable('2020-01-01T00:00:00Z');
        $this->now = new \DateTimeImmutable('2023-06-15T12:00:00Z');
        $this->future = new \DateTimeImmutable('2030-12-31T23:59:59Z');
    }

    public function testDateWithinInclusiveRangePasses(): void
    {
        $rule = new DateTimeRangeRule(new DateTimeRange(min: $this->past, max: $this->future));
        $this->assertEmpty($rule->validate($this->now, 'x', 'x'));
    }

    public function testDateAtInclusiveMinBoundaryPasses(): void
    {
        $rule = new DateTimeRangeRule(new DateTimeRange(min: $this->past));
        $this->assertEmpty($rule->validate($this->past, 'x', 'x'));
    }

    public function testDateBelowInclusiveMinFails(): void
    {
        $rule = new DateTimeRangeRule(new DateTimeRange(min: $this->now));
        $errors = $rule->validate($this->past, 'x', 'x');
        $this->assertCount(1, $errors);
        $this->assertSame('out_of_range', $errors[0]->code->value);
    }

    public function testDateAtExclusiveMinFails(): void
    {
        $rule = new DateTimeRangeRule(new DateTimeRange(min: $this->now, bounds: '()'));
        $errors = $rule->validate($this->now, 'x', 'x');
        $this->assertCount(1, $errors);
    }

    public function testDateAtInclusiveMaxBoundaryPasses(): void
    {
        $rule = new DateTimeRangeRule(new DateTimeRange(max: $this->future));
        $this->assertEmpty($rule->validate($this->future, 'x', 'x'));
    }

    public function testDateAboveInclusiveMaxFails(): void
    {
        $rule = new DateTimeRangeRule(new DateTimeRange(max: $this->now));
        $errors = $rule->validate($this->future, 'x', 'x');
        $this->assertCount(1, $errors);
        $this->assertSame('out_of_range', $errors[0]->code->value);
    }

    public function testDateAtExclusiveMaxFails(): void
    {
        $rule = new DateTimeRangeRule(new DateTimeRange(max: $this->now, bounds: '()'));
        $errors = $rule->validate($this->now, 'x', 'x');
        $this->assertCount(1, $errors);
    }

    public function testCrossedMinMaxRangeIsRejectedAtConstruction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('min must not be greater than max');

        new DateTimeRange(min: $this->future, max: $this->past);
    }

    public function testKeyAndPathArePreservedInError(): void
    {
        $rule = new DateTimeRangeRule(new DateTimeRange(min: $this->future));
        $errors = $rule->validate($this->past, 'createdAt', 'event.createdAt');
        $this->assertSame('createdAt', $errors[0]->key);
        $this->assertSame('event.createdAt', $errors[0]->fullPath);
    }

    public function testNoBoundsAlwaysPasses(): void
    {
        $rule = new DateTimeRangeRule(new DateTimeRange());
        $this->assertEmpty($rule->validate($this->past, 'x', 'x'));
        $this->assertEmpty($rule->validate($this->future, 'x', 'x'));
    }

    public function testLeftClosedRightOpenBoundsAtMaxFails(): void
    {
        // [past, now) — now is excluded
        $rule = new DateTimeRangeRule(new DateTimeRange(min: $this->past, max: $this->now, bounds: '[)'));
        $this->assertCount(1, $rule->validate($this->now, 'x', 'x'));
        $this->assertEmpty($rule->validate($this->past, 'x', 'x'));
    }

    public function testLeftOpenRightClosedBoundsAtMinFails(): void
    {
        // (past, now] — past is excluded
        $rule = new DateTimeRangeRule(new DateTimeRange(min: $this->past, max: $this->now, bounds: '(]'));
        $this->assertCount(1, $rule->validate($this->past, 'x', 'x'));
        $this->assertEmpty($rule->validate($this->now, 'x', 'x'));
    }

    public function testMutableDateTimeWorks(): void
    {
        $min = new \DateTime('2020-01-01T00:00:00Z');
        $max = new \DateTime('2030-01-01T00:00:00Z');
        $value = new \DateTime('2025-06-01T00:00:00Z');

        $rule = new DateTimeRangeRule(new DateTimeRange(min: $min, max: $max));
        $this->assertEmpty($rule->validate($value, 'x', 'x'));
    }

    public function testErrorMessageContainsAtomFormattedBound(): void
    {
        $min = new \DateTimeImmutable('2030-01-01T00:00:00+00:00');
        $rule = new DateTimeRangeRule(new DateTimeRange(min: $min));
        $errors = $rule->validate($this->past, 'x', 'x');
        $this->assertStringContainsString('2030-01-01', $errors[0]->programmerMessage);
    }

    public function testExclusiveMinUsesStrictOperatorInMessage(): void
    {
        $rule = new DateTimeRangeRule(new DateTimeRange(min: $this->now, bounds: '()'));
        $errors = $rule->validate($this->now, 'x', 'x');
        $this->assertStringContainsString('must be >', $errors[0]->programmerMessage);
    }

    public function testExclusiveMaxUsesStrictOperatorInMessage(): void
    {
        $rule = new DateTimeRangeRule(new DateTimeRange(max: $this->now, bounds: '()'));
        $errors = $rule->validate($this->now, 'x', 'x');
        $this->assertStringContainsString('must be <', $errors[0]->programmerMessage);
    }
}
