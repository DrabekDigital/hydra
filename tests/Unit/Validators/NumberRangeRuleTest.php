<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\Validators;

use DrabekDigital\Hydra\Attributes\Validation\NumberRange;
use DrabekDigital\Hydra\Validators\Rules\NumberRangeRule;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class NumberRangeRuleTest extends TestCase
{
    // ── min ──────────────────────────────────────────────────────────

    public function testValueAboveInclusiveMinPasses(): void
    {
        $rule = new NumberRangeRule(new NumberRange(min: 5));
        $this->assertEmpty($rule->validate(10, 'x', 'x'));
    }

    public function testValueAtInclusiveMinPasses(): void
    {
        $rule = new NumberRangeRule(new NumberRange(min: 5));
        $this->assertEmpty($rule->validate(5, 'x', 'x'));
    }

    public function testValueBelowInclusiveMinFails(): void
    {
        $rule = new NumberRangeRule(new NumberRange(min: 5));
        $errors = $rule->validate(4, 'x', 'x');
        $this->assertCount(1, $errors);
        $this->assertSame('out_of_range', $errors[0]->code->value);
    }

    public function testValueAtExclusiveMinFails(): void
    {
        $rule = new NumberRangeRule(new NumberRange(min: 5, bounds: '()'));
        $errors = $rule->validate(5, 'x', 'x');
        $this->assertCount(1, $errors);
    }

    public function testValueAboveExclusiveMinPasses(): void
    {
        $rule = new NumberRangeRule(new NumberRange(min: 5, bounds: '()'));
        $this->assertEmpty($rule->validate(6, 'x', 'x'));
    }

    // ── max ──────────────────────────────────────────────────────────

    public function testValueBelowInclusiveMaxPasses(): void
    {
        $rule = new NumberRangeRule(new NumberRange(max: 10));
        $this->assertEmpty($rule->validate(9, 'x', 'x'));
    }

    public function testValueAtInclusiveMaxPasses(): void
    {
        $rule = new NumberRangeRule(new NumberRange(max: 10));
        $this->assertEmpty($rule->validate(10, 'x', 'x'));
    }

    public function testValueAboveInclusiveMaxFails(): void
    {
        $rule = new NumberRangeRule(new NumberRange(max: 10));
        $errors = $rule->validate(11, 'x', 'x');
        $this->assertCount(1, $errors);
        $this->assertSame('out_of_range', $errors[0]->code->value);
    }

    public function testValueAtExclusiveMaxFails(): void
    {
        $rule = new NumberRangeRule(new NumberRange(max: 10, bounds: '()'));
        $errors = $rule->validate(10, 'x', 'x');
        $this->assertCount(1, $errors);
    }

    // ── both bounds ───────────────────────────────────────────────────

    public function testValueInsideRangePasses(): void
    {
        $rule = new NumberRangeRule(new NumberRange(min: 1, max: 10));
        $this->assertEmpty($rule->validate(5, 'x', 'x'));
    }

    public function testCrossedMinMaxRangeIsRejectedAtConstruction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('min must not be greater than max');

        new NumberRange(min: 5, max: 3);
    }

    public function testFloatValueIsSupported(): void
    {
        $rule = new NumberRangeRule(new NumberRange(min: 1.5, max: 2.5));
        $this->assertEmpty($rule->validate(2.0, 'x', 'x'));
        $errors = $rule->validate(3.0, 'x', 'x');
        $this->assertCount(1, $errors);
    }

    public function testKeyAndPathArePreservedInError(): void
    {
        $rule = new NumberRangeRule(new NumberRange(min: 10));
        $errors = $rule->validate(1, 'age', 'user.age');
        $this->assertSame('age', $errors[0]->key);
        $this->assertSame('user.age', $errors[0]->fullPath);
    }

    // ── mixed bounds ──────────────────────────────────────────────────

    public function testLeftClosedRightOpenBoundsAtMaxFails(): void
    {
        // [5, 10) — 10 is excluded
        $rule = new NumberRangeRule(new NumberRange(min: 5, max: 10, bounds: '[)'));
        $errors = $rule->validate(10, 'x', 'x');
        $this->assertCount(1, $errors);
    }

    public function testLeftClosedRightOpenBoundsAtMinPasses(): void
    {
        $rule = new NumberRangeRule(new NumberRange(min: 5, max: 10, bounds: '[)'));
        $this->assertEmpty($rule->validate(5, 'x', 'x'));
        $this->assertEmpty($rule->validate(9, 'x', 'x'));
    }

    public function testLeftOpenRightClosedBoundsAtMinFails(): void
    {
        // (5, 10] — 5 is excluded
        $rule = new NumberRangeRule(new NumberRange(min: 5, max: 10, bounds: '(]'));
        $errors = $rule->validate(5, 'x', 'x');
        $this->assertCount(1, $errors);
    }

    public function testLeftOpenRightClosedBoundsAtMaxPasses(): void
    {
        $rule = new NumberRangeRule(new NumberRange(min: 5, max: 10, bounds: '(]'));
        $this->assertEmpty($rule->validate(10, 'x', 'x'));
        $this->assertEmpty($rule->validate(6, 'x', 'x'));
    }

    // ── no-bounds / single-bound ──────────────────────────────────────

    public function testNoBoundsAlwaysPasses(): void
    {
        $rule = new NumberRangeRule(new NumberRange());
        $this->assertEmpty($rule->validate(-999, 'x', 'x'));
        $this->assertEmpty($rule->validate(0, 'x', 'x'));
        $this->assertEmpty($rule->validate(999, 'x', 'x'));
    }

    public function testOnlyMinSetDoesNotConstrainMax(): void
    {
        $rule = new NumberRangeRule(new NumberRange(min: 0));
        $this->assertEmpty($rule->validate(PHP_INT_MAX, 'x', 'x'));
    }

    public function testOnlyMaxSetDoesNotConstrainMin(): void
    {
        $rule = new NumberRangeRule(new NumberRange(max: 100));
        $this->assertEmpty($rule->validate(PHP_INT_MIN, 'x', 'x'));
    }

    // ── negative values ───────────────────────────────────────────────

    public function testNegativeRangeWorks(): void
    {
        $rule = new NumberRangeRule(new NumberRange(min: -10, max: -1));
        $this->assertEmpty($rule->validate(-5, 'x', 'x'));
        $this->assertCount(1, $rule->validate(0, 'x', 'x'));
        $this->assertCount(1, $rule->validate(-11, 'x', 'x'));
    }

    public function testZeroAsMin(): void
    {
        $rule = new NumberRangeRule(new NumberRange(min: 0));
        $this->assertEmpty($rule->validate(0, 'x', 'x'));
        $this->assertCount(1, $rule->validate(-1, 'x', 'x'));
    }

    public function testExclusiveMinUsesStrictOperatorInMessage(): void
    {
        $rule = new NumberRangeRule(new NumberRange(min: 5, bounds: '()'));
        $errors = $rule->validate(5, 'x', 'x');
        $this->assertStringContainsString('must be >', $errors[0]->programmerMessage);
    }

    public function testExclusiveMaxUsesStrictOperatorInMessage(): void
    {
        $rule = new NumberRangeRule(new NumberRange(max: 10, bounds: '()'));
        $errors = $rule->validate(10, 'x', 'x');
        $this->assertStringContainsString('must be <', $errors[0]->programmerMessage);
    }
}
