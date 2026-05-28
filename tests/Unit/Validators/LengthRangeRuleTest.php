<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\Validators;

use DrabekDigital\Hydra\Attributes\Validation\LengthRange;
use DrabekDigital\Hydra\Validators\Rules\LengthRangeRule;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class LengthRangeRuleTest extends TestCase
{
    public function testStringWithinRangePasses(): void
    {
        $rule = new LengthRangeRule(new LengthRange(min: 2, max: 10));
        $this->assertEmpty($rule->validate('hello', 'x', 'x'));
    }

    public function testStringAtMinBoundaryPasses(): void
    {
        $rule = new LengthRangeRule(new LengthRange(min: 3));
        $this->assertEmpty($rule->validate('abc', 'x', 'x'));
    }

    public function testStringBelowMinFails(): void
    {
        $rule = new LengthRangeRule(new LengthRange(min: 5));
        $errors = $rule->validate('hi', 'x', 'x');
        $this->assertCount(1, $errors);
        $this->assertSame('too_short', $errors[0]->code->value);
    }

    public function testStringAtMaxBoundaryPasses(): void
    {
        $rule = new LengthRangeRule(new LengthRange(max: 3));
        $this->assertEmpty($rule->validate('abc', 'x', 'x'));
    }

    public function testStringAboveMaxFails(): void
    {
        $rule = new LengthRangeRule(new LengthRange(max: 3));
        $errors = $rule->validate('toolong', 'x', 'x');
        $this->assertCount(1, $errors);
        $this->assertSame('too_long', $errors[0]->code->value);
    }

    public function testCrossedMinMaxRangeIsRejectedAtConstruction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('min (10) must not be greater than max (2)');

        new LengthRange(min: 10, max: 2);
    }

    public function testMultibyteCharactersCountedCorrectly(): void
    {
        // "héllo" is 5 characters, not 6
        $rule = new LengthRangeRule(new LengthRange(min: 5, max: 5));
        $this->assertEmpty($rule->validate('héllo', 'x', 'x'));
    }

    public function testEmptyStringBelowMinFails(): void
    {
        $rule = new LengthRangeRule(new LengthRange(min: 1));
        $errors = $rule->validate('', 'x', 'x');
        $this->assertCount(1, $errors);
        $this->assertSame('too_short', $errors[0]->code->value);
    }

    public function testKeyAndPathArePreservedInError(): void
    {
        $rule = new LengthRangeRule(new LengthRange(min: 10));
        $errors = $rule->validate('x', 'name', 'user.name');
        $this->assertSame('name', $errors[0]->key);
        $this->assertSame('user.name', $errors[0]->fullPath);
    }

    public function testNoBoundsAlwaysPasses(): void
    {
        $rule = new LengthRangeRule(new LengthRange());
        $this->assertEmpty($rule->validate('', 'x', 'x'));
        $this->assertEmpty($rule->validate(str_repeat('a', 1000), 'x', 'x'));
    }

    public function testOnlyMinSetDoesNotConstrainLongStrings(): void
    {
        $rule = new LengthRangeRule(new LengthRange(min: 1));
        $this->assertEmpty($rule->validate(str_repeat('x', 10000), 'x', 'x'));
    }

    public function testOnlyMaxSetAllowsEmptyString(): void
    {
        $rule = new LengthRangeRule(new LengthRange(max: 5));
        $this->assertEmpty($rule->validate('', 'x', 'x'));
    }

    public function testMaxZeroAllowsEmptyOnly(): void
    {
        $rule = new LengthRangeRule(new LengthRange(max: 0));
        $this->assertEmpty($rule->validate('', 'x', 'x'));
        $this->assertCount(1, $rule->validate('a', 'x', 'x'));
    }

    public function testEmojisCountedAsOneCharacter(): void
    {
        // Each emoji is one Unicode code point, mb_strlen counts them as 1
        $rule = new LengthRangeRule(new LengthRange(min: 2, max: 2));
        $this->assertEmpty($rule->validate('😀😀', 'x', 'x'));
    }

    public function testMinAndMaxSameValueExactMatchPasses(): void
    {
        $rule = new LengthRangeRule(new LengthRange(min: 5, max: 5));
        $this->assertEmpty($rule->validate('hello', 'x', 'x'));
        $this->assertCount(1, $rule->validate('hi', 'x', 'x'));
        $this->assertCount(1, $rule->validate('toolong', 'x', 'x'));
    }
}
