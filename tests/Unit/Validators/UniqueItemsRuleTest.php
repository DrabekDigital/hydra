<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\Validators;

use DrabekDigital\Hydra\Attributes\Validation\UniqueItems;
use DrabekDigital\Hydra\Tests\Fixtures\Validators\IdentifiableObject;
use DrabekDigital\Hydra\Validators\Rules\UniqueItemsRule;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class UniqueItemsRuleTest extends TestCase
{
    public function testAllUniqueScalarsPasses(): void
    {
        $rule = new UniqueItemsRule(new UniqueItems());
        $this->assertEmpty($rule->validate([1, 2, 3, 'a'], 'x', 'x'));
    }

    public function testEmptyArrayPasses(): void
    {
        $rule = new UniqueItemsRule(new UniqueItems());
        $this->assertEmpty($rule->validate([], 'x', 'x'));
    }

    public function testSingleItemArrayPasses(): void
    {
        $rule = new UniqueItemsRule(new UniqueItems());
        $this->assertEmpty($rule->validate([42], 'x', 'x'));
    }

    public function testDuplicateIntegersFail(): void
    {
        $rule = new UniqueItemsRule(new UniqueItems());
        $errors = $rule->validate([1, 2, 1], 'x', 'x');
        $this->assertCount(1, $errors);
        $this->assertSame('not_unique', $errors[0]->code->value);
    }

    public function testDuplicateStringsFail(): void
    {
        $rule = new UniqueItemsRule(new UniqueItems());
        $errors = $rule->validate(['a', 'b', 'a'], 'x', 'x');
        $this->assertCount(1, $errors);
    }

    public function testTypeStrictness(): void
    {
        // '1' !== 1, so this should pass
        $rule = new UniqueItemsRule(new UniqueItems());
        $this->assertEmpty($rule->validate([1, '1'], 'x', 'x'));
    }

    public function testOnlyOneErrorEvenWithManyDuplicates(): void
    {
        $rule = new UniqueItemsRule(new UniqueItems());
        $errors = $rule->validate([1, 1, 1, 1], 'x', 'x');
        $this->assertCount(1, $errors);
    }

    public function testCustomComparatorIsUsed(): void
    {
        // Case-insensitive comparison
        $comparator = fn (mixed $a, mixed $b): bool => is_string($a) && is_string($b) && strtolower($a) === strtolower($b);
        $rule = new UniqueItemsRule(new UniqueItems(comparator: $comparator));

        $errors = $rule->validate(['Hello', 'hello'], 'x', 'x');
        $this->assertCount(1, $errors);
        $this->assertEmpty($rule->validate(['Hello', 'World'], 'x', 'x'));
    }

    public function testKeyAndPathArePreservedInError(): void
    {
        $rule = new UniqueItemsRule(new UniqueItems());
        $errors = $rule->validate([1, 1], 'ids', 'order.ids');
        $this->assertSame('ids', $errors[0]->key);
        $this->assertSame('order.ids', $errors[0]->fullPath);
    }

    public function testNullValuesAreDuplicates(): void
    {
        // null === null is true
        $rule = new UniqueItemsRule(new UniqueItems());
        $errors = $rule->validate([null, null], 'x', 'x');
        $this->assertCount(1, $errors);
    }

    public function testNullAndFalseAreDistinct(): void
    {
        $rule = new UniqueItemsRule(new UniqueItems());
        $this->assertEmpty($rule->validate([null, false, 0, ''], 'x', 'x'));
    }

    public function testFloatDuplicatesFail(): void
    {
        $rule = new UniqueItemsRule(new UniqueItems());
        $this->assertCount(1, $rule->validate([1.5, 2.5, 1.5], 'x', 'x'));
    }

    public function testIdenticalArrayValuesAreDuplicates(): void
    {
        // In PHP, [1,2] === [1,2]
        $rule = new UniqueItemsRule(new UniqueItems());
        $errors = $rule->validate([[1, 2], [3, 4], [1, 2]], 'x', 'x');
        $this->assertCount(1, $errors);
    }

    public function testCustomComparatorAlwaysFalse(): void
    {
        // Comparator that never considers two items equal — everything passes
        $rule = new UniqueItemsRule(new UniqueItems(comparator: fn () => false));
        $this->assertEmpty($rule->validate([1, 1, 1], 'x', 'x'));
    }

    public function testCustomComparatorAlwaysTrue(): void
    {
        // Single-element array: loop body never runs, so it always passes
        $rule = new UniqueItemsRule(new UniqueItems(comparator: fn () => true));
        $this->assertEmpty($rule->validate([1], 'x', 'x'));
        // Two elements: comparator fires immediately
        $this->assertCount(1, $rule->validate([1, 2], 'x', 'x'));
    }

    public function testBooleanDuplicates(): void
    {
        $rule = new UniqueItemsRule(new UniqueItems());
        $this->assertCount(1, $rule->validate([true, true], 'x', 'x'));
        $this->assertEmpty($rule->validate([true, false], 'x', 'x'));
    }

    public function testObjectsWithoutComparatorFailEvenWhenAllInstancesAreUnique(): void
    {
        $rule = new UniqueItemsRule(new UniqueItems());
        $errors = $rule->validate([new \stdClass(), new \stdClass()], 'items', 'payload.items');

        $this->assertCount(1, $errors);
        $this->assertSame('unique_items_comparator_required', $errors[0]->code->value);
        $this->assertSame('items', $errors[0]->key);
        $this->assertSame('payload.items', $errors[0]->fullPath);
    }

    public function testNestedObjectWithoutComparatorFails(): void
    {
        $rule = new UniqueItemsRule(new UniqueItems());
        $errors = $rule->validate([['id' => new \stdClass()], ['id' => new \stdClass()]], 'x', 'x');

        $this->assertCount(1, $errors);
        $this->assertSame('unique_items_comparator_required', $errors[0]->code->value);
    }

    public function testObjectsWithComparatorCanDetectDuplicates(): void
    {
        $a = new IdentifiableObject(1);
        $b = new IdentifiableObject(1);

        $rule = new UniqueItemsRule(new UniqueItems(
            comparator: static fn (IdentifiableObject $left, IdentifiableObject $right): bool => $left->id === $right->id,
        ));

        $this->assertCount(1, $rule->validate([$a, $b], 'x', 'x'));
        $this->assertEmpty($rule->validate([$a, new IdentifiableObject(2)], 'x', 'x'));
    }
}
