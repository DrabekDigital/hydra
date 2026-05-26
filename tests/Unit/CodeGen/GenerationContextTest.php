<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\CodeGen;

use DrabekDigital\Hydra\Attributes\Rename;
use DrabekDigital\Hydra\Attributes\Skip;
use DrabekDigital\Hydra\CodeGen\GenerationContext;
use DrabekDigital\Hydra\Metadata\PropertyMetadata;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class GenerationContextTest extends TestCase
{
    // ── inputKey / outputKey ──────────────────────────────────────────

    public function testInputKeyDefaultsToPropertyName(): void
    {
        $ctx = $this->makeContext([
            $this->makeProperty('firstName'),
        ]);
        $this->assertSame('firstName', $ctx->inputKey('firstName'));
    }

    public function testOutputKeyDefaultsToPropertyName(): void
    {
        $ctx = $this->makeContext([
            $this->makeProperty('lastName'),
        ]);
        $this->assertSame('lastName', $ctx->outputKey('lastName'));
    }

    public function testRenameFromOverridesInputKey(): void
    {
        $ctx = $this->makeContext([
            $this->makeProperty('firstName', rename: new Rename(from: 'first_name')),
        ]);
        $this->assertSame('first_name', $ctx->inputKey('firstName'));
    }

    public function testRenameToOverridesOutputKey(): void
    {
        $ctx = $this->makeContext([
            $this->makeProperty('lastName', rename: new Rename(to: 'last_name')),
        ]);
        $this->assertSame('last_name', $ctx->outputKey('lastName'));
    }

    public function testRenameFromDoesNotAffectOutputKey(): void
    {
        $ctx = $this->makeContext([
            $this->makeProperty('firstName', rename: new Rename(from: 'first_name')),
        ]);
        $this->assertSame('firstName', $ctx->outputKey('firstName'));
    }

    public function testRenameToDoesNotAffectInputKey(): void
    {
        $ctx = $this->makeContext([
            $this->makeProperty('lastName', rename: new Rename(to: 'last_name')),
        ]);
        $this->assertSame('lastName', $ctx->inputKey('lastName'));
    }

    public function testUnknownPropertyNameFallsBackToName(): void
    {
        $ctx = $this->makeContext([]);
        $this->assertSame('ghost', $ctx->inputKey('ghost'));
        $this->assertSame('ghost', $ctx->outputKey('ghost'));
    }

    // ── inputKeys ─────────────────────────────────────────────────────

    public function testInputKeysReturnsAllMappedKeys(): void
    {
        $ctx = $this->makeContext([
            $this->makeProperty('firstName', rename: new Rename(from: 'first_name')),
            $this->makeProperty('age'),
        ]);
        $this->assertSame(['first_name', 'age'], $ctx->inputKeys());
    }

    // ── shouldSkipHydration / shouldSkipDehydration ───────────────────

    public function testSkipHydrationTrueWhenFlagSet(): void
    {
        $ctx = $this->makeContext([]);
        $prop = $this->makeProperty('x', skip: new Skip(hydration: true, dehydration: false));
        $this->assertTrue($ctx->shouldSkipHydration($prop));
        $this->assertFalse($ctx->shouldSkipDehydration($prop));
    }

    public function testSkipDehydrationTrueWhenFlagSet(): void
    {
        $ctx = $this->makeContext([]);
        $prop = $this->makeProperty('x', skip: new Skip(hydration: false, dehydration: true));
        $this->assertFalse($ctx->shouldSkipHydration($prop));
        $this->assertTrue($ctx->shouldSkipDehydration($prop));
    }

    public function testSkipBothByDefault(): void
    {
        $ctx = $this->makeContext([]);
        $prop = $this->makeProperty('x', skip: new Skip());
        $this->assertTrue($ctx->shouldSkipHydration($prop));
        $this->assertTrue($ctx->shouldSkipDehydration($prop));
    }

    public function testNoSkipAttributeMeansNotSkipped(): void
    {
        $ctx = $this->makeContext([]);
        $prop = $this->makeProperty('x');
        $this->assertFalse($ctx->shouldSkipHydration($prop));
        $this->assertFalse($ctx->shouldSkipDehydration($prop));
    }

    // ── isNamespaced / typeIsNamespaced / getShortName ────────────────

    public function testIsNamespacedTrueForQualifiedName(): void
    {
        $ctx = new GenerationContext(
            className: GenerationContext::class,
            shortName: 'GenerationContext',
            hasUndefinable: false,
            properties: [],
        );
        $this->assertTrue($ctx->isNamespaced());
    }

    public function testTypeIsNamespacedStaticMethod(): void
    {
        $this->assertTrue(GenerationContext::typeIsNamespaced('Foo\Bar'));
        $this->assertFalse(GenerationContext::typeIsNamespaced('Bar'));
    }

    public function testGetShortNameExtractsLastSegment(): void
    {
        $this->assertSame('MyClass', GenerationContext::getShortName('Foo\Bar\MyClass'));
        $this->assertSame('Simple', GenerationContext::getShortName('Simple'));
    }

    // ── nested types ──────────────────────────────────────────────────

    public function testRegisterNestedTypeAndRetrieve(): void
    {
        $ctx = $this->makeContext([]);
        $ctx->registerNestedType('Foo\Bar');
        $ctx->registerNestedType('Baz\Qux');

        $types = $ctx->getNestedTypes();
        $this->assertContains('Foo\Bar', $types);
        $this->assertContains('Baz\Qux', $types);
    }

    public function testRegisterSameTypeTwiceDeduplicates(): void
    {
        $ctx = $this->makeContext([]);
        $ctx->registerNestedType('Foo\Bar');
        $ctx->registerNestedType('Foo\Bar');

        $this->assertCount(1, $ctx->getNestedTypes());
    }

    public function testGetNestedTypesEmptyByDefault(): void
    {
        $ctx = $this->makeContext([]);
        $this->assertSame([], $ctx->getNestedTypes());
    }

    // ── dehydrationErrorsGenerated ────────────────────────────────────

    public function testDehydrationErrorsGeneratedFalseByDefault(): void
    {
        $ctx = $this->makeContext([]);
        $this->assertFalse($ctx->dehydrationErrorsGenerated());
    }

    public function testMarkDehydrationErrorsGeneratedFlipsFlag(): void
    {
        $ctx = $this->makeContext([]);
        $ctx->markDehydrationErrorsGenerated();
        $this->assertTrue($ctx->dehydrationErrorsGenerated());
    }

    // ── helpers ──────────────────────────────────────────────────────

    /** @param array<PropertyMetadata> $properties */
    private function makeContext(array $properties): GenerationContext
    {
        return new GenerationContext(
            className: \stdClass::class,
            shortName: 'stdClass',
            hasUndefinable: false,
            properties: $properties,
        );
    }

    private function makeProperty(string $name, ?Rename $rename = null, ?Skip $skip = null): PropertyMetadata
    {
        $attributes = [];
        if ($rename !== null) {
            $attributes[Rename::class] = $rename;
        }
        if ($skip !== null) {
            $attributes[Skip::class] = $skip;
        }

        return new PropertyMetadata(
            name: $name,
            types: ['string'],
            isNullable: false,
            hasDefault: false,
            defaultValue: null,
            isBuiltin: true,
            attributes: $attributes,
        );
    }
}
