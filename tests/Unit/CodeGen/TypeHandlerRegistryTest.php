<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\CodeGen;

use DrabekDigital\Hydra\CodeGen\GenerationContext;
use DrabekDigital\Hydra\CodeGen\TypeHandlers\TypeHandler;
use DrabekDigital\Hydra\CodeGen\TypeHandlers\TypeHandlerRegistry;
use DrabekDigital\Hydra\Metadata\PropertyMetadata;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class TypeHandlerRegistryTest extends TestCase
{
    public function testGetHandlerReturnsMatchingHandler(): void
    {
        $registry = new TypeHandlerRegistry();
        $handler = $this->makeHandler(supports: true, priority: 0);
        $registry->register($handler);

        $result = $registry->getHandler($this->makeProperty(), $this->makeContext());
        $this->assertSame($handler, $result);
    }

    public function testGetHandlerThrowsWhenNoHandlerMatches(): void
    {
        $registry = new TypeHandlerRegistry();
        $registry->register($this->makeHandler(supports: false, priority: 0));

        $this->expectException(\RuntimeException::class);
        $registry->getHandler($this->makeProperty('age', ['int']), $this->makeContext());
    }

    public function testGetHandlerErrorMessageContainsPropertyName(): void
    {
        $registry = new TypeHandlerRegistry();

        try {
            $registry->getHandler($this->makeProperty('myProp', ['CustomType']), $this->makeContext());
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('myProp', $e->getMessage());
            $this->assertStringContainsString('CustomType', $e->getMessage());
        }
    }

    public function testHigherPriorityHandlerSelectedFirst(): void
    {
        $registry = new TypeHandlerRegistry();
        $low = $this->makeHandler(supports: true, priority: 10);
        $high = $this->makeHandler(supports: true, priority: 100);

        // Register low-priority first — registry must sort
        $registry->register($low);
        $registry->register($high);

        $result = $registry->getHandler($this->makeProperty(), $this->makeContext());
        $this->assertSame($high, $result);
    }

    public function testHighPriorityNonMatchingHandlerSkipped(): void
    {
        $registry = new TypeHandlerRegistry();
        $nonMatch = $this->makeHandler(supports: false, priority: 200);
        $match = $this->makeHandler(supports: true, priority: 10);

        $registry->register($nonMatch);
        $registry->register($match);

        $result = $registry->getHandler($this->makeProperty(), $this->makeContext());
        $this->assertSame($match, $result);
    }

    public function testHasHandlerReturnsTrueWhenHandlerExists(): void
    {
        $registry = new TypeHandlerRegistry();
        $registry->register($this->makeHandler(supports: true, priority: 0));

        $this->assertTrue($registry->hasHandler($this->makeProperty(), $this->makeContext()));
    }

    public function testHasHandlerReturnsFalseWhenNoHandlerMatches(): void
    {
        $registry = new TypeHandlerRegistry();
        $registry->register($this->makeHandler(supports: false, priority: 0));

        $this->assertFalse($registry->hasHandler($this->makeProperty(), $this->makeContext()));
    }

    public function testEmptyRegistryHasNoHandler(): void
    {
        $registry = new TypeHandlerRegistry();
        $this->assertFalse($registry->hasHandler($this->makeProperty(), $this->makeContext()));
    }

    public function testRegisterReturnsSelfForChaining(): void
    {
        $registry = new TypeHandlerRegistry();
        $result = $registry->register($this->makeHandler(supports: true, priority: 0));
        $this->assertSame($registry, $result);
    }

    public function testSortingIsStableAfterMultipleRegistrations(): void
    {
        $registry = new TypeHandlerRegistry();
        $p50 = $this->makeHandler(supports: true, priority: 50);
        $p200 = $this->makeHandler(supports: true, priority: 200);
        $p100 = $this->makeHandler(supports: true, priority: 100);

        $registry->register($p50)->register($p200)->register($p100);

        // Must return the highest-priority matching handler
        $this->assertSame($p200, $registry->getHandler($this->makeProperty(), $this->makeContext()));
    }

    // ── helpers ──────────────────────────────────────────────────────

    private function makeHandler(bool $supports, int $priority): TypeHandler
    {
        return new class($supports, $priority) implements TypeHandler {
            public function __construct(private bool $supports, private int $prio) {}

            public function priority(): int
            {
                return $this->prio;
            }

            public function supports(PropertyMetadata $p, GenerationContext $c): bool
            {
                return $this->supports;
            }

            public function generateHydration(PropertyMetadata $p, GenerationContext $c): array
            {
                return [];
            }

            public function generateDehydration(PropertyMetadata $p, GenerationContext $c): array
            {
                return [];
            }
        };
    }

    /** @param list<string> $types */
    private function makeProperty(string $name = 'prop', array $types = ['string']): PropertyMetadata
    {
        return new PropertyMetadata(
            name: $name,
            types: $types,
            isNullable: false,
            hasDefault: false,
            defaultValue: null,
            isBuiltin: true,
        );
    }

    private function makeContext(): GenerationContext
    {
        return new GenerationContext(
            className: \stdClass::class,
            hasUndefinable: false,
            properties: [],
        );
    }
}
