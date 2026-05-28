<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\Validators;

use DrabekDigital\Hydra\Attributes\Validation\Count;
use DrabekDigital\Hydra\Validators\Rules\CountRule;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class CountRuleTest extends TestCase
{
    public function testArrayWithinRangePasses(): void
    {
        $rule = new CountRule(new Count(min: 1, max: 5));
        $this->assertEmpty($rule->validate([1, 2, 3], 'x', 'x'));
    }

    public function testArrayAtMinBoundaryPasses(): void
    {
        $rule = new CountRule(new Count(min: 2));
        $this->assertEmpty($rule->validate([1, 2], 'x', 'x'));
    }

    public function testArrayBelowMinFails(): void
    {
        $rule = new CountRule(new Count(min: 3));
        $errors = $rule->validate([1], 'x', 'x');
        $this->assertCount(1, $errors);
        $this->assertSame('count_too_low', $errors[0]->code->value);
    }

    public function testArrayAtMaxBoundaryPasses(): void
    {
        $rule = new CountRule(new Count(max: 3));
        $this->assertEmpty($rule->validate([1, 2, 3], 'x', 'x'));
    }

    public function testArrayAboveMaxFails(): void
    {
        $rule = new CountRule(new Count(max: 2));
        $errors = $rule->validate([1, 2, 3], 'x', 'x');
        $this->assertCount(1, $errors);
        $this->assertSame('count_too_high', $errors[0]->code->value);
    }

    public function testEmptyArrayBelowMinFails(): void
    {
        $rule = new CountRule(new Count(min: 1));
        $errors = $rule->validate([], 'x', 'x');
        $this->assertCount(1, $errors);
        $this->assertSame('count_too_low', $errors[0]->code->value);
    }

    public function testCrossedMinMaxRangeIsRejectedAtConstruction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('min (5) must not be greater than max (1)');

        new Count(min: 5, max: 1);
    }

    public function testKeyAndPathArePreservedInError(): void
    {
        $rule = new CountRule(new Count(min: 5));
        $errors = $rule->validate([], 'tags', 'post.tags');
        $this->assertSame('tags', $errors[0]->key);
        $this->assertSame('post.tags', $errors[0]->fullPath);
    }

    public function testNoBoundsAlwaysPasses(): void
    {
        $rule = new CountRule(new Count());
        $this->assertEmpty($rule->validate([], 'x', 'x'));
        $this->assertEmpty($rule->validate(array_fill(0, 1000, null), 'x', 'x'));
    }

    public function testOnlyMinSetDoesNotConstrainLargeArrays(): void
    {
        $rule = new CountRule(new Count(min: 1));
        $this->assertEmpty($rule->validate(array_fill(0, 100, 'x'), 'x', 'x'));
    }

    public function testOnlyMaxSetAllowsEmptyArray(): void
    {
        $rule = new CountRule(new Count(max: 10));
        $this->assertEmpty($rule->validate([], 'x', 'x'));
    }

    public function testAssociativeArrayCountedCorrectly(): void
    {
        $rule = new CountRule(new Count(min: 2, max: 3));
        $this->assertEmpty($rule->validate(['a' => 1, 'b' => 2], 'x', 'x'));
        $this->assertCount(1, $rule->validate(['only' => 1], 'x', 'x'));
    }

    public function testNestedArrayCountsTopLevelOnly(): void
    {
        // [[1,2,3], [4,5,6]] has count 2, not 6
        $rule = new CountRule(new Count(min: 2, max: 2));
        $this->assertEmpty($rule->validate([[1, 2, 3], [4, 5, 6]], 'x', 'x'));
    }
}
