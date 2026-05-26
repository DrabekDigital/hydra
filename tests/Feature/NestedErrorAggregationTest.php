<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Feature;

use DrabekDigital\Hydra\Attributes\ListOf;
use DrabekDigital\Hydra\Exceptions\HydrationFailure;
use DrabekDigital\Hydra\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class NestedErrorAggregationTest extends TestCase
{
    public function testNestedHydrationErrorsAreAggregatedIntoParent(): void
    {
        // Both fields of InnerAgg fail — both errors must appear in the parent failure
        try {
            $this->hydrator->hydrate(
                ['inner' => ['x' => 99, 'y' => 'bad']],
                OuterAggDTO::class
            );
            $this->fail('Expected HydrationFailure');
        } catch (HydrationFailure $e) {
            $errors = $e->getErrors();
            $this->assertCount(2, $errors);

            $paths = array_map(fn ($err) => $err->fullPath, $errors);
            $this->assertContains('inner.x', $paths);
            $this->assertContains('inner.y', $paths);
        }
    }

    public function testMultipleNestedObjectsAggregateAllErrors(): void
    {
        // Two nested objects, each with a bad field — 4 errors total
        try {
            $this->hydrator->hydrate(
                [
                    'a' => ['x' => 1, 'y' => 'bad'],
                    'b' => ['x' => 2, 'y' => 'bad'],
                ],
                TwoNestingDTO::class
            );
            $this->fail('Expected HydrationFailure');
        } catch (HydrationFailure $e) {
            $errors = $e->getErrors();
            $this->assertCount(4, $errors);

            $paths = array_map(fn ($err) => $err->fullPath, $errors);
            $this->assertContains('a.x', $paths);
            $this->assertContains('a.y', $paths);
            $this->assertContains('b.x', $paths);
            $this->assertContains('b.y', $paths);
        }
    }

    public function testNestedObjectSuccessDoesNotAffectParentErrors(): void
    {
        // Only the parent-level error appears when nested object hydrates fine
        $this->assertHydrationFails(
            ['a' => ['x' => 'ok', 'y' => 1], 'b' => 'not_an_array'],
            TwoNestingDTO::class,
            'type_mismatch',
            'b'
        );
    }

    public function testListOfNestedObjectsAggregatesErrors(): void
    {
        // Three list items, middle one fails — error is aggregated
        try {
            $this->hydrator->hydrate(
                [
                    'items' => [
                        ['x' => 'ok', 'y' => 1],
                        ['x' => 99, 'y' => 'bad'],
                        ['x' => 'ok', 'y' => 3],
                    ],
                ],
                ListAggDTO::class
            );
            $this->fail('Expected HydrationFailure');
        } catch (HydrationFailure $e) {
            $errors = $e->getErrors();
            $this->assertCount(2, $errors);

            $codes = array_map(fn ($err) => $err->code->value, $errors);
            $this->assertContains('type_mismatch', $codes);
        }
    }
}

final class InnerAggDTO
{
    public function __construct(
        public readonly string $x,
        public readonly int $y,
    ) {}
}

final class OuterAggDTO
{
    public function __construct(
        public readonly InnerAggDTO $inner,
    ) {}
}

final class TwoNestingDTO
{
    public function __construct(
        public readonly InnerAggDTO $a,
        public readonly InnerAggDTO $b,
    ) {}
}

final class ListAggDTO
{
    public function __construct(
        /** @var array<InnerAggDTO> */
        #[ListOf(value: InnerAggDTO::class)]
        public readonly array $items,
    ) {}
}
