<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\Metadata;

use DrabekDigital\Hydra\Attributes\Validation\NumberRange;
use DrabekDigital\Hydra\Metadata\PropertyMetadata;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class PropertyMetadataTest extends TestCase
{
    // ── isRequired ────────────────────────────────────────────────────

    public function testRequiredWhenNoDefaultNotNullableNotUndefinable(): void
    {
        $meta = $this->make(isNullable: false, hasDefault: false, hasAttributeDefault: false, isUndefinable: false);
        $this->assertTrue($meta->isRequired());
    }

    public function testNotRequiredWhenNullable(): void
    {
        $meta = $this->make(isNullable: true);
        $this->assertFalse($meta->isRequired());
    }

    public function testNotRequiredWhenHasPhpDefault(): void
    {
        $meta = $this->make(hasDefault: true, defaultValue: 'default');
        $this->assertFalse($meta->isRequired());
    }

    public function testNotRequiredWhenHasAttributeDefault(): void
    {
        $meta = $this->make(hasAttributeDefault: true, attributeDefaultValue: 42);
        $this->assertFalse($meta->isRequired());
    }

    public function testNotRequiredWhenUndefinable(): void
    {
        $meta = $this->make(isUndefinable: true);
        $this->assertFalse($meta->isRequired());
    }

    // ── hasAnyDefault ─────────────────────────────────────────────────

    public function testHasAnyDefaultWhenPhpDefault(): void
    {
        $meta = $this->make(hasDefault: true);
        $this->assertTrue($meta->hasAnyDefault());
    }

    public function testHasAnyDefaultWhenAttributeDefault(): void
    {
        $meta = $this->make(hasAttributeDefault: true);
        $this->assertTrue($meta->hasAnyDefault());
    }

    public function testHasAnyDefaultWhenBoth(): void
    {
        $meta = $this->make(hasDefault: true, hasAttributeDefault: true);
        $this->assertTrue($meta->hasAnyDefault());
    }

    public function testNoDefaultWhenNeither(): void
    {
        $meta = $this->make(hasDefault: false, hasAttributeDefault: false);
        $this->assertFalse($meta->hasAnyDefault());
    }

    // ── getEffectiveDefault ───────────────────────────────────────────

    public function testAttributeDefaultTakesPrecedenceOverPhpDefault(): void
    {
        $meta = $this->make(hasDefault: true, defaultValue: 'php', hasAttributeDefault: true, attributeDefaultValue: 'attr');
        $this->assertSame('attr', $meta->getEffectiveDefault());
    }

    public function testPhpDefaultReturnedWhenNoAttributeDefault(): void
    {
        $meta = $this->make(hasDefault: true, defaultValue: 'php-value', hasAttributeDefault: false);
        $this->assertSame('php-value', $meta->getEffectiveDefault());
    }

    public function testNullReturnedWhenNoDefault(): void
    {
        $meta = $this->make(hasDefault: false, defaultValue: null, hasAttributeDefault: false);
        $this->assertNull($meta->getEffectiveDefault());
    }

    // ── getPrimaryType ────────────────────────────────────────────────

    public function testPrimaryTypeIsFirstType(): void
    {
        $meta = $this->make(types: ['string', 'int']);
        $this->assertSame('string', $meta->getPrimaryType());
    }

    public function testPrimaryTypeIsNullWhenNoTypes(): void
    {
        $meta = $this->make(types: []);
        $this->assertNull($meta->getPrimaryType());
    }

    // ── hasAttribute / getAttribute ───────────────────────────────────

    public function testHasAttributeReturnsTrueWhenPresent(): void
    {
        $attr = new NumberRange(min: 1);
        $meta = $this->make(attributes: [NumberRange::class => $attr]);
        $this->assertTrue($meta->hasAttribute(NumberRange::class));
    }

    public function testHasAttributeReturnsFalseWhenAbsent(): void
    {
        $meta = $this->make();
        $this->assertFalse($meta->hasAttribute(NumberRange::class));
    }

    public function testGetAttributeReturnsInstanceWhenPresent(): void
    {
        $attr = new NumberRange(min: 5, max: 10);
        $meta = $this->make(attributes: [NumberRange::class => $attr]);
        $result = $meta->getAttribute(NumberRange::class);
        $this->assertSame($attr, $result);
    }

    public function testGetAttributeReturnsNullWhenAbsent(): void
    {
        $meta = $this->make();
        $this->assertNull($meta->getAttribute(NumberRange::class));
    }

    public function testGetAttributeReturnsNullWhenWrongType(): void
    {
        // Attribute stored under the wrong key — defensive check in getAttribute
        $meta = $this->make(attributes: [NumberRange::class => new \stdClass()]);
        $this->assertNull($meta->getAttribute(NumberRange::class));
    }

    // ── helper ───────────────────────────────────────────────────────

    /**
     * @param array<string>         $types
     * @param array<string, object> $attributes
     */
    private function make(
        string $name = 'prop',
        array $types = ['string'],
        bool $isNullable = false,
        bool $hasDefault = false,
        mixed $defaultValue = null,
        bool $isBuiltin = true,
        bool $hasAttributeDefault = false,
        mixed $attributeDefaultValue = null,
        bool $isUndefinable = false,
        mixed $undefinedDehydrationValue = null,
        array $attributes = [],
    ): PropertyMetadata {
        return new PropertyMetadata(
            name: $name,
            types: $types,
            isNullable: $isNullable,
            hasDefault: $hasDefault,
            defaultValue: $defaultValue,
            isBuiltin: $isBuiltin,
            hasAttributeDefault: $hasAttributeDefault,
            attributeDefaultValue: $attributeDefaultValue,
            isUndefinable: $isUndefinable,
            undefinedDehydrationValue: $undefinedDehydrationValue,
            attributes: $attributes,
        );
    }
}
