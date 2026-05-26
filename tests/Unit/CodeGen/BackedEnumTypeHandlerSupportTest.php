<?php

declare(strict_types=1);

namespace DrabekDigital\Hydra\Tests\Unit\CodeGen;

use DrabekDigital\Hydra\CodeGen\GenerationContext;
use DrabekDigital\Hydra\CodeGen\TypeHandlers\BackedEnumTypeHandler;
use DrabekDigital\Hydra\Metadata\PropertyMetadata;
use DrabekDigital\Hydra\Tests\Fixtures\Enums\Priority;
use DrabekDigital\Hydra\Tests\Fixtures\Enums\Status;
use PHPUnit\Framework\TestCase;

enum UnitColorSwatch
{
    case Red;
    case Green;
    case Blue;
}

/**
 * @internal
 *
 * @coversNothing
 */
class BackedEnumTypeHandlerSupportTest extends TestCase
{
    private BackedEnumTypeHandler $handler;
    private GenerationContext $ctx;

    protected function setUp(): void
    {
        $this->handler = new BackedEnumTypeHandler();
        $this->ctx = new GenerationContext(
            className: \stdClass::class,
            shortName: 'stdClass',
            hasUndefinable: false,
            properties: [],
        );
    }

    public function testStringBackedEnumIsSupported(): void
    {
        $this->assertTrue($this->handler->supports($this->prop(Status::class), $this->ctx));
    }

    public function testIntBackedEnumIsSupported(): void
    {
        $this->assertTrue($this->handler->supports($this->prop(Priority::class), $this->ctx));
    }

    public function testUnitEnumIsNotSupported(): void
    {
        $this->assertFalse($this->handler->supports($this->prop(UnitColorSwatch::class), $this->ctx));
    }

    public function testPlainClassIsNotSupported(): void
    {
        $this->assertFalse($this->handler->supports($this->prop(\stdClass::class), $this->ctx));
    }

    public function testScalarTypeIsNotSupported(): void
    {
        $this->assertFalse($this->handler->supports($this->prop('string'), $this->ctx));
    }

    public function testNonExistentTypeIsNotSupported(): void
    {
        $this->assertFalse($this->handler->supports($this->prop('NonExistent\Enum'), $this->ctx));
    }

    public function testEmptyTypesListIsNotSupported(): void
    {
        $prop = new PropertyMetadata(
            name: 'x',
            types: [],
            isNullable: false,
            hasDefault: false,
            defaultValue: null,
            isBuiltin: false,
        );
        $this->assertFalse($this->handler->supports($prop, $this->ctx));
    }

    public function testPriorityIsAboveObjectHandler(): void
    {
        // ObjectTypeHandler has priority 70 — BackedEnum must beat it
        $this->assertGreaterThan(70, $this->handler->priority());
    }

    private function prop(string $type): PropertyMetadata
    {
        return new PropertyMetadata(
            name: 'x',
            types: [$type],
            isNullable: false,
            hasDefault: false,
            defaultValue: null,
            isBuiltin: false,
        );
    }
}
